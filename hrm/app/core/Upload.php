<?php
declare(strict_types=1);

namespace App\Core;

final class Upload
{
    private const MAX_BYTES = 5_242_880;
    private const ALLOWED = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
    ];

    /** Lưu tài liệu bằng tên ngẫu nhiên, chỉ cho phép PDF/JPEG/PNG tối đa 5 MB. */
    public static function save(array $file, string $category): ?array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new \DomainException('Tải tệp lên thất bại. Vui lòng thử lại.');
        }
        if (!isset($file['tmp_name'], $file['name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new \DomainException('Tệp tải lên không hợp lệ.');
        }
        if ((int) ($file['size'] ?? 0) < 1 || (int) $file['size'] > self::MAX_BYTES) {
            throw new \DomainException('Tệp phải có dung lượng từ 1 byte đến 5 MB.');
        }

        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset(self::ALLOWED[$extension]) || !in_array($mime, self::ALLOWED[$extension], true)) {
            throw new \DomainException('Chỉ chấp nhận tệp PDF, JPG hoặc PNG hợp lệ.');
        }

        $relativeDirectory = $category . '/' . date('Y') . '/' . date('m');
        $directory = APP_ROOT . '/uploads/' . $relativeDirectory;
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new \RuntimeException('Không thể tạo thư mục lưu tài liệu.');
        }

        $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
        $destination = $directory . '/' . $storedName;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new \RuntimeException('Không thể lưu tệp đã tải lên.');
        }

        return [
            'stored' => $relativeDirectory . '/' . $storedName,
            'original' => mb_substr(basename((string) $file['name']), 0, 180, 'UTF-8'),
            'mime' => $mime,
            'size' => (int) $file['size'],
            'uploaded_at' => date('Y-m-d H:i:s'),
        ];
    }
}
