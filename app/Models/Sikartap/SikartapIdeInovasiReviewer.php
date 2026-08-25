<?php

namespace App\Models\Sikartap;

use App\Models\Master\Companies;
use App\Models\Master\OrgStructure;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Penunjukan manual reviewer atasan Ide Inovasi per node Divisi (level 2) org struktur — lihat
 * migration create_sikartap_ide_inovasi_reviewers_table untuk alasan kenapa ini perlu (head_id
 * kosong), dan Sikartap::ideInovasiOrgDivision() untuk kenapa Divisi bukan Department (level 1). */
class SikartapIdeInovasiReviewer extends Model
{
    use HasUlids;

    protected $table = 'sikartap_ide_inovasi_reviewers';

    protected $fillable = ['company_id', 'org_structure_id', 'user_id'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class);
    }

    public function orgStructure(): BelongsTo
    {
        return $this->belongsTo(OrgStructure::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
