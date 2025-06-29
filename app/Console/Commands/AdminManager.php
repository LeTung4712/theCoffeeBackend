<?php
namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminManager extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:manage';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Quản lý tài khoản admin (xem thông tin và đổi password)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== ADMIN MANAGER ===');

        while (true) {
            $this->newLine();
            $this->line('1. Xem thông tin admin hiện tại');
            $this->line('2. Đổi password admin');
            $this->line('3. Thoát');
            $this->newLine();

            $choice = $this->ask('Chọn (1-3)');

            switch ($choice) {
                case '1':
                    $this->showAdminInfo();
                    break;
                case '2':
                    $this->resetPassword();
                    break;
                case '3':
                    $this->info('Tạm biệt!');
                    return 0;
                default:
                    $this->error('Lựa chọn không hợp lệ!');
            }

            $this->newLine();
            $this->line(str_repeat('-', 30));
        }
    }

    private function showAdminInfo()
    {
        $this->newLine();
        $this->info('=== THÔNG TIN ADMIN ===');

        $admin = Admin::first();

        if (! $admin) {
            $this->error('❌ Không tìm thấy admin nào!');
            return;
        }

        $this->info('✅ Tìm thấy admin:');
        $this->line("   ID: {$admin->id}");
        $this->line("   Tên đăng nhập: {$admin->username}");
        $this->line("   Ngày tạo: {$admin->created_at}");
        $this->line("   Cập nhật lần cuối: {$admin->updated_at}");
    }

    private function resetPassword()
    {
        $this->newLine();
        $this->info('=== ĐỔI PASSWORD ADMIN ===');

        $admin = Admin::first();

        if (! $admin) {
            $this->error('❌ Không tìm thấy admin nào!');
            return;
        }

        $this->line("Admin hiện tại: {$admin->id} ({$admin->username})");
        $this->newLine();

        $this->line('Chọn cách tạo password:');
        $this->line('1. Tự động tạo password ngẫu nhiên');
        $this->line('2. Nhập password thủ công');
        $this->line('3. Hủy');
        $this->newLine();

        $choice = $this->ask('Chọn (1-3)');

        switch ($choice) {
            case '1':
                $newPassword = $this->generateStrongPassword();
                $this->info("Password mới: {$newPassword}");
                break;
            case '2':
                $this->newLine();
                $this->warn('Yêu cầu password:');
                $this->line('   - Ít nhất 6 ký tự');
                $this->line('   - Có chữ hoa (A-Z)');
                $this->line('   - Có chữ thường (a-z)');
                $this->line('   - Có số (0-9)');
                $this->line('   - Có ký tự đặc biệt (@#$%^&*!...)');
                $this->newLine();
                $newPassword = $this->secret('Nhập password mới');
                if (! $this->validatePassword($newPassword)) {
                    $this->error('❌ Password phải có ít nhất 6 ký tự và chứa:');
                    $this->error('- Chữ hoa (A-Z) - Chữ thường (a-z) - Số (0-9) - Ký tự đặc biệt (@#$%^&*!...)');
                    return;
                }
                break;
            case '3':
                $this->info('Đã hủy!');
                return;
            default:
                $this->error('Lựa chọn không hợp lệ!');
                return;
        }

        $this->newLine();
        $this->warn('Xác nhận đổi password?');
        $this->line("   Tên đăng nhập: {$admin->username}");
        $this->line("   Password mới: {$newPassword}");
        $this->newLine();

        if (! $this->confirm('Bạn có chắc chắn muốn tiếp tục?')) {
            $this->info('Đã hủy đổi password!');
            return;
        }

        try {
            $admin->update([
                'password'                 => Hash::make($newPassword),
                'access_token'             => null,
                'refresh_token'            => null,
                'refresh_token_expired_at' => null,
            ]);

            $this->newLine();
            $this->info('✅ Đổi password thành công!');
            $this->line('📝 Thông tin đăng nhập mới:');
            $this->line("   Tên đăng nhập: {$admin->username}");
            $this->line("   Password: {$newPassword}");
            $this->newLine();
            $this->warn('⚠️  Hãy lưu thông tin này ở nơi an toàn!');
            $this->info('🔒 Tất cả token đã được xóa để đảm bảo bảo mật');

            // Log hoạt động
            \Log::info('Admin password reset', [
                'admin_id'       => $admin->id,
                'admin_username' => $admin->username,
                'reset_by'       => 'admin_manager_command',
                'tokens_cleared' => true,
                'timestamp'      => now(),
            ]);

        } catch (\Exception $e) {
            $this->error("❌ Lỗi khi đổi password: " . $e->getMessage());
        }
    }

    private function validatePassword($password)
    {
        // Kiểm tra độ dài tối thiểu
        if (strlen($password) < 6) {
            return false;
        }

        // Kiểm tra chữ hoa
        if (! preg_match('/[A-Z]/', $password)) {
            return false;
        }

        // Kiểm tra chữ thường
        if (! preg_match('/[a-z]/', $password)) {
            return false;
        }

        // Kiểm tra số
        if (! preg_match('/[0-9]/', $password)) {
            return false;
        }

        // Kiểm tra ký tự đặc biệt
        if (! preg_match('/[@#$%^&*!()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
            return false;
        }

        return true;
    }

    private function generateStrongPassword()
    {
        $length = 12;

        // Đảm bảo có ít nhất 1 ký tự của mỗi loại
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers   = '0123456789';
        $special   = '@#$%^&*';

        $password = '';

        // Thêm 1 ký tự của mỗi loại
        $password .= $uppercase[rand(0, strlen($uppercase) - 1)];
        $password .= $lowercase[rand(0, strlen($lowercase) - 1)];
        $password .= $numbers[rand(0, strlen($numbers) - 1)];
        $password .= $special[rand(0, strlen($special) - 1)];

        // Thêm các ký tự ngẫu nhiên còn lại
        $allCharacters = $uppercase . $lowercase . $numbers . $special;
        for ($i = 4; $i < $length; $i++) {
            $password .= $allCharacters[rand(0, strlen($allCharacters) - 1)];
        }

        // Trộn ngẫu nhiên password
        return str_shuffle($password);
    }
}
