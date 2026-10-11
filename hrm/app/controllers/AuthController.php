<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Models\Account;
use PDOException;

final class AuthController extends Controller
{
    public function login(): void
    {
        if (Auth::check()) {
            $this->redirect('dashboard');
        }

        $this->render('auth/login', ['title' => 'Đăng nhập']);
    }

    public function forgotPassword(): void
    {
        if (Auth::check()) {
            $this->redirect('dashboard');
        }

        $this->render('auth/forgot-password', ['title' => 'Quên mật khẩu']);
    }

    public function sendResetLink(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            flash('error', 'Vui lòng nhập địa chỉ email hợp lệ.');
            $this->redirect('forgot-password');
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $db = Database::connection();
        $accounts = Account::connect();

        try {
            $db->beginTransaction();
            $recipient = $accounts->createRecoveryToken($email, $tokenHash);
            $db->commit();
        } catch (PDOException $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }

        if ($recipient !== null) {
            $resetUrl = url('reset-password') . '&token=' . rawurlencode($token);
            $subject = '=?UTF-8?B?' . base64_encode('Đặt lại mật khẩu HT MEDIA') . '?=';
            $body = "Bạn đã yêu cầu đặt lại mật khẩu.\n\n"
                . "Mở liên kết sau trong vòng 1 giờ để tạo mật khẩu mới:\n"
                . $resetUrl . "\n\n"
                . "Nếu bạn không yêu cầu thao tác này, hãy bỏ qua email.";
            $from = getenv('HRM_MAIL_FROM') ?: 'no-reply@localhost';
            $sent = filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false
                && filter_var($from, FILTER_VALIDATE_EMAIL) !== false
                && mail(
                    $recipient,
                    $subject,
                    $body,
                    "From: {$from}\r\nContent-Type: text/plain; charset=UTF-8"
                );

            if (!$sent) {
                try {
                    $db->beginTransaction();
                    $accounts->clearRecoveryToken($tokenHash);
                    $db->commit();
                } catch (PDOException $exception) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    throw $exception;
                }
                error_log('HRM password reset email could not be sent by PHP mail().');
            }
        }

        flash(
            'success',
            'Nếu email thuộc về một tài khoản đang hoạt động, hướng dẫn đặt lại mật khẩu sẽ được gửi đến hộp thư.'
        );
        $this->redirect('forgot-password');
    }

    public function resetPassword(): void
    {
        if (Auth::check()) {
            $this->redirect('dashboard');
        }

        header('Referrer-Policy: no-referrer');
        header('Cache-Control: no-store, private');
        $token = (string) ($_GET['token'] ?? '');
        if (!$this->isValidResetToken($token)) {
            flash('error', 'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.');
            $this->redirect('forgot-password');
        }

        $this->render('auth/reset-password', [
            'title' => 'Đặt lại mật khẩu',
            'token' => $token,
        ]);
    }

    public function updatePassword(): void
    {
        $token = (string) ($_POST['token'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');

        if (!$this->isValidResetToken($token)) {
            flash('error', 'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.');
            $this->redirect('forgot-password');
        }
        if (strlen($password) < 12) {
            flash('error', 'Mật khẩu mới phải có ít nhất 12 ký tự.');
            $this->redirectToResetForm($token);
        }
        if (!hash_equals($password, $confirmation)) {
            flash('error', 'Mật khẩu xác nhận không khớp.');
            $this->redirectToResetForm($token);
        }

        $db = Database::connection();
        $accounts = Account::connect();
        try {
            $db->beginTransaction();
            $updated = $accounts->resetPassword(
                hash('sha256', $token),
                password_hash($password, PASSWORD_DEFAULT)
            );
            $db->commit();
        } catch (PDOException $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }

        if (!$updated) {
            flash('error', 'Liên kết đặt lại mật khẩu đã hết hạn hoặc đã được sử dụng.');
            $this->redirect('forgot-password');
        }

        flash('success', 'Mật khẩu đã được cập nhật. Vui lòng đăng nhập.');
        $this->redirect('login');
    }

    public function authenticate(): void
    {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            flash('error', 'Vui lòng nhập tên đăng nhập và mật khẩu.');
            $this->redirect('login');
        }

        $db = Database::connection();
        $accounts = Account::connect();

        try {
            $db->beginTransaction();
            $account = $accounts->findForLogin($username);

            if ($account === null) {
                $db->commit();
                flash('error', 'Tên đăng nhập hoặc mật khẩu không đúng.');
                $this->redirect('login');
            }

            $locked = (bool) $account['DangBiKhoa'];
            if ($locked) {
                $db->commit();
                flash('error', 'Tài khoản đang bị khóa. Vui lòng thử lại sau 15 phút.');
                $this->redirect('login');
            }

            $active = $account['TrangThai'] === 'Hoạt động';
            $expiredLock = $account['KhoaDen'] !== null && !$locked;
            if (!$active || !password_verify($password, (string) $account['MatKhau'])) {
                $failures = $expiredLock ? 1 : (int) $account['SoLanSai'] + 1;
                $shouldLock = $failures >= 5;
                $accounts->recordFailure((string) $account['MaTK'], $failures, $shouldLock);
                $db->commit();

                flash(
                    'error',
                    $shouldLock
                        ? 'Tài khoản đã bị khóa 15 phút do nhập sai mật khẩu 5 lần.'
                        : 'Tên đăng nhập hoặc mật khẩu không đúng.'
                );
                $this->redirect('login');
            }

            $accounts->resetFailures((string) $account['MaTK']);
            $db->commit();
            $roles = [(string) $account['VaiTro']];
            $isHead = (bool) $account['IsTruongBP'];
            if ($isHead) {
                $roles[] = 'quanly';
            }
            Auth::login([
                'MaTK' => (string) $account['MaTK'],
                'MaNV' => (string) $account['MaNV'],
                'HoTen' => (string) $account['HoTen'],
                'VaiTro' => (string) $account['VaiTro'],
                'ma_nv' => (string) $account['MaNV'],
                'ma_pb' => (string) $account['MaPB'],
                'vai_tro' => array_values(array_unique($roles)),
                'is_truong_bp' => $isHead,
            ]);
            $this->redirect('dashboard');
        } catch (PDOException $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    public function logout(): void
    {
        Auth::requireLogin();
        Auth::logout();
        header('Location: ' . url('login'));
        exit;
    }

    private function isValidResetToken(string $token): bool
    {
        return preg_match('/^[a-f0-9]{64}$/', $token) === 1
            && Account::connect()->hasValidRecoveryToken(hash('sha256', $token));
    }

    private function redirectToResetForm(string $token): void
    {
        header('Location: ' . url('reset-password') . '&token=' . rawurlencode($token));
        exit;
    }
}
