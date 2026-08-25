<?php

namespace App\Services\Sarpras;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Models\ApprovalManagement\ApprovalSnapshots;
use App\Models\Sarpras\SarprasRequest;
use App\Models\Sarpras\SarprasRequestItem;
use App\Models\Sarpras\SarprasRequestSignature;
use App\Models\User;
use App\Repositories\Approval\ApprovalSnapshotRepository;
use App\Repositories\Sarpras\SarprasRequestRepository;
use App\Services\Approval\ApprovalCreationService;
use App\Services\Approval\ApprovalFlowService;
use RuntimeException;

class SarprasRequestService
{
    public function __construct(
        protected SarprasRequestRepository $repository,
        protected ApprovalSnapshotRepository $approvalSnapshotRepository,
        protected ApprovalCreationService $approvalCreationService,
        protected ApprovalFlowService $approvalFlowService,
    ) {}

    /**
     * `unit` TIDAK diminta dari input — diambil otomatis dari Divisi (OrgStructure level 2)
     * pemohon (UserProfiles::division), bukan pilihan bebas. Kalau pemohon belum terhubung ke
     * struktur organisasi manapun, submit ditolak (lihat guard di bawah).
     *
     * @param array{jenis: string, prioritas: string, perihal: string, uraian: string,
     *              items: array<int, array{nama: string, spesifikasi?: ?string, jumlah: int, satuan?: string, harga_satuan?: ?float}>,
     *              attachments?: array<int, \Illuminate\Http\UploadedFile>} $data
     */
    public function submit(array $data, User $actingUser): SarprasRequest
    {
        $flow = $this->approvalSnapshotRepository->findMasterApprovalFlowByCompany(
            ApprovalModuleEnum::SARPRAS_REQUEST->value,
            $actingUser->company_id,
        );

        if (! $flow) {
            throw new RuntimeException('Belum ada Approval Flow aktif untuk Sarana & Prasarana di company ini.');
        }

        $unit = $actingUser->profile?->division?->name;

        if (! $unit) {
            throw new RuntimeException('Anda belum terhubung ke Divisi manapun di struktur organisasi. Hubungi HC untuk mengatur ini sebelum mengajukan.');
        }

        $request = $this->repository->store([
            'user_id' => $actingUser->id,
            'kode' => $this->generateKode($actingUser->company_id),
            'unit' => $unit,
            'jenis' => $data['jenis'] ?? 'perbaikan',
            'prioritas' => $data['prioritas'] ?? 'normal',
            'perihal' => $data['perihal'],
            'uraian' => $data['uraian'],
            'status' => 'diajukan',
        ]);

        $this->repository->storeItems($request, $data['items']);

        if (! empty($data['attachments'])) {
            $this->repository->storeAttachments($request, $data['attachments'], $actingUser->id);
        }

        $this->bubuhkanTtd($request, 'unit', $actingUser);

        $this->approvalCreationService->createSnapshot(
            moduleType: ApprovalModuleEnum::SARPRAS_REQUEST->value,
            moduleId: $request->id,
            flow: $flow,
            createdBy: $actingUser->id,
            companyId: $actingUser->company_id,
            userId: $actingUser->id,
        );

        return $request->fresh();
    }

    /** Aksi Kabid: meneruskan permohonan ke Direktur (Kabid tidak bisa menolak, hanya meneruskan). */
    public function teruskan(SarprasRequest $request, User $actingUser, ?string $catatan = null): SarprasRequest
    {
        if ($request->status !== 'diajukan') {
            throw new RuntimeException('Permohonan ini sudah diteruskan sebelumnya.');
        }

        $this->bubuhkanTtd($request, 'kabid', $actingUser);

        $snapshot = ApprovalSnapshots::find($request->approvalSnapshot->id);
        $this->approvalFlowService->advanceStep(
            snapshot: $snapshot,
            actorId: $actingUser->id,
            note: $catatan,
            action: 'approved',
        );

        $this->repository->updateRequest($request, [
            'catatan_kabid' => $catatan,
            'kabid_id' => $actingUser->id,
            'kabid_at' => now(),
            'status' => $request->computeStatus(),
        ]);

        return $request->fresh();
    }

    /**
     * Aksi Direktur: putuskan SATU item (setuju penuh/sebagian jumlah, atau tolak). Item yang
     * sudah diputuskan terkunci. Begitu semua item di request ini sudah diputuskan (tidak ada
     * lagi yang `menunggu`), baru sekali itu Approval Engine di-advance ke final (approved kalau
     * minimal 1 item disetujui, rejected kalau semua ditolak) — bukan tiap item.
     */
    public function putuskanItem(
        SarprasRequestItem $item,
        string $keputusan,
        ?int $jumlahDisetujui,
        ?string $catatan,
        User $actingUser,
    ): SarprasRequestItem {
        if ($item->status !== 'menunggu') {
            throw new RuntimeException('Item ini sudah diputuskan dan tidak dapat diubah lagi.');
        }

        $jumlahDisetujui = $keputusan === 'disetujui'
            ? min($jumlahDisetujui ?? $item->jumlah, $item->jumlah)
            : null;

        $this->repository->updateItem($item, [
            'status' => $keputusan,
            'jumlah_disetujui' => $jumlahDisetujui,
            'catatan_direktur' => $catatan,
            'diputuskan_at' => now(),
        ]);

        $request = $item->request()->first();
        $newStatus = $request->computeStatus();

        $masihMenunggu = $request->items()->where('status', 'menunggu')->exists();

        if ($masihMenunggu) {
            $this->repository->updateRequest($request, ['status' => $newStatus]);

            return $item->fresh();
        }

        // Semua item sudah diputuskan — finalisasi step Direktur di Approval Engine, sekali saja
        // (guard direktur_at null mencegah advanceStep() terpanggil dua kali kalau method ini
        // ke-invoke lagi setelah item terakhir, mis. race/retry).
        if ($request->direktur_at === null) {
            $this->bubuhkanTtd($request, 'direktur', $actingUser);

            $engineAction = $request->items()->where('status', 'disetujui')->exists() ? 'approved' : 'rejected';

            $snapshot = ApprovalSnapshots::find($request->approvalSnapshot->id);
            $this->approvalFlowService->advanceStep(
                snapshot: $snapshot,
                actorId: $actingUser->id,
                note: $catatan,
                action: $engineAction,
            );

            $this->repository->updateRequest($request, [
                'catatan_direktur' => $catatan,
                'direktur_id' => $actingUser->id,
                'direktur_at' => now(),
                'status' => $newStatus,
            ]);
        }

        return $item->fresh();
    }

    /** Aksi Unit: tandai pengerjaan/pengadaan sudah dituntaskan. */
    public function selesaikan(SarprasRequest $request, User $actingUser): SarprasRequest
    {
        if (! in_array($request->status, ['disetujui', 'disetujui_sebagian'], true)) {
            throw new RuntimeException('Permohonan ini belum bisa ditutup — belum disetujui (sebagian).');
        }

        $this->repository->updateRequest($request, [
            'status' => 'selesai',
            'selesai_at' => now(),
        ]);

        return $request->fresh();
    }

    /**
     * Bubuhkan tanda tangan elektronik — idempoten, 1x per peran per dokumen (dijamin juga oleh
     * unique constraint DB). Kode barcode-nya sendiri dibangkitkan di sini (string pendek, format
     * porting dari another-app/sarpras); rendering SVG-nya menyusul di langkah barcode/cetak.
     */
    private function bubuhkanTtd(SarprasRequest $request, string $peran, User $user): SarprasRequestSignature
    {
        $existing = $request->signatures()->where('peran', $peran)->first();
        if ($existing) {
            return $existing;
        }

        $nomor = preg_replace('/\D/', '', $request->kode);
        $inisial = strtoupper($peran[0]);

        do {
            $kode = $nomor . $inisial . '-' . strtoupper(bin2hex(random_bytes(3)));
            $bentrok = SarprasRequestSignature::where('kode', $kode)->exists();
        } while ($bentrok);

        return $this->repository->storeSignature($request, $peran, $user, $kode);
    }

    /**
     * `kode` unique GLOBAL di DB (tidak ada kolom company_id di sarpras_requests, lihat
     * migrasi) — tapi nomor urutnya dihitung PER COMPANY (biar tiap RS mulai dari 0001 sendiri,
     * bukan berbagi 1 urutan global yang aneh dilihat user). Makanya kode company WAJIB masuk
     * format-nya, kalau tidak 2 company yang sama-sama bikin permohonan pertama tahun ini bakal
     * tabrakan di constraint unique (SPR-2026-0001 vs SPR-2026-0001).
     */
    private function generateKode(string $companyId): string
    {
        $companyCode = \App\Models\Master\Companies::find($companyId)?->code ?? 'XX';
        $tahun = now()->year;
        $urut = SarprasRequest::whereYear('created_at', $tahun)
            ->whereHas('user', fn ($q) => $q->where('company_id', $companyId))
            ->count() + 1;

        return sprintf('SPR-%s-%d-%04d', $companyCode, $tahun, $urut);
    }
}
