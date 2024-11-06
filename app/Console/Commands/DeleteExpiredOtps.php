<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Otp;
use Carbon\Carbon;

class DeleteExpiredOtps extends Command
{
    protected $signature = 'otp:delete-expired';
    protected $description = 'حذف کدهای منقضی شده از دیتابیس';

    public function handle()
    {
        $deletedCount = Otp::query()->where('expires_at', '<', Carbon::now())->delete();

        $this->info("تعداد {$deletedCount} کد منقضی شده حذف شد.");
    }
}
