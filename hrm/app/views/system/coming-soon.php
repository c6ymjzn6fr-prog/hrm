<div class="d-flex align-items-center gap-3 mb-4">
    <div class="summary-icon bg-primary-subtle text-primary">
        <i class="bi bi-cone-striped"></i>
    </div>
    <div>
        <h1 class="h3 mb-1"><?= e($moduleTitle) ?></h1>
        <p class="text-secondary mb-0">Khu vực chức năng theo quyền tài khoản.</p>
    </div>
</div>
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <p class="mb-2">Phân hệ này đã có trong cấu trúc điều hướng nhưng nghiệp vụ chưa được triển khai.</p>
        <p class="small text-secondary mb-0">
            <?php if ($canManage): ?>
                Tài khoản của bạn có quyền quản lý phân hệ này.
            <?php else: ?>
                Nội dung sẽ hiển thị theo quyền được cấp khi phân hệ hoàn thiện.
            <?php endif; ?>
        </p>
    </div>
</div>
