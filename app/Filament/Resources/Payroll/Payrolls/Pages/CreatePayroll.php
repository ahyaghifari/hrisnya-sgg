<?php

namespace App\Filament\Resources\Payroll\Payrolls\Pages;

use App\Filament\Resources\Payroll\Payrolls\PayrollResource;
use App\Helpers\PermissionHelper;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Employee\Employee;
use App\Models\User;
use App\Services\Payroll\PayrollService;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreatePayroll extends CreateRecord
{
    protected static string $resource = PayrollResource::class;

    protected static ?string $title = 'Buat Payroll';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('payroll_period_id')
                    ->label('Periode Payroll')
                    ->options(function () {
                        $user = Auth::user();

                        $query = PayrollPeriod::query()->with('company')->orderByDesc('start_date');

                        if ($user && $user->company_id && ! PermissionHelper::hasGlobalRole($user)) {
                            $query->where('company_id', $user->company_id);
                        }

                        return $query->get()->mapWithKeys(fn (PayrollPeriod $period) => [
                            $period->id => $period->company->name . ' — '
                                . $period->start_date->format('d M Y') . ' s/d ' . $period->end_date->format('d M Y'),
                        ]);
                    })
                    ->required()
                    ->live()
                    ->searchable(),

                Select::make('employee_id')
                    ->label('Karyawan')
                    ->options(function (Get $get) {
                        $period = PayrollPeriod::find($get('payroll_period_id'));

                        if (! $period) {
                            return [];
                        }

                        // Super Admin bukan penerima gaji; Presiden Direktur & Sekretaris Direktur tetap
                        // bisa dipilih (User::PAYROLL_ELIGIBLE_EXECUTIVE_IDS).
                        return Employee::query()
                            ->where('company_id', $period->company_id)
                            ->where('status', 'aktif')
                            ->whereNotNull('user_id')
                            ->whereNotIn('user_id', array_diff(User::NON_KARYAWAN_IDS, User::PAYROLL_ELIGIBLE_EXECUTIVE_IDS))
                            ->whereNotIn('id', Payroll::where('payroll_period_id', $period->id)->select('employee_id'))
                            ->orderBy('nama_lengkap')
                            ->pluck('nama_lengkap', 'id');
                    })                    ->required()
                    ->searchable()
                    ->disabled(fn (Get $get) => blank($get('payroll_period_id')))
                    ->helperText('Cuma karyawan yang belum punya payroll di periode ini yang muncul.'),
            ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $period = PayrollPeriod::findOrFail($data['payroll_period_id']);
        $employee = Employee::findOrFail($data['employee_id']);

        try {
            return app(PayrollService::class)->generateSingleForEmployee($employee, $period, Auth::id());
        } catch (\RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            throw new Halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
