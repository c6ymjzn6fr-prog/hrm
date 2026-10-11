<?php
$departmentLabels = array_map(static fn (array $row): string => (string) $row['TenPB'], $departments);
$departmentCounts = array_map(static fn (array $row): int => (int) $row['SoLuong'], $departments);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 mb-1">Tổng quan · <?= e($role) ?></h1>
        <p class="text-secondary mb-0">Xin chào, <?= e($user['HoTen'] ?? '') ?>.</p>
    </div>
    <nav class="d-flex flex-wrap gap-2" aria-label="Chuyển vai trò">
        <?php foreach ($roles as $availableRole): ?>
            <a class="btn btn-sm <?= $role === $availableRole ? 'btn-primary' : 'btn-outline-primary' ?>"
               href="<?= e(url('dashboard')) ?>&role=<?= e($availableRole) ?>"><?= e($availableRole) ?></a>
        <?php endforeach; ?>
    </nav>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($cards as $card): ?>
        <div class="col-12 col-sm-6 col-xl">
            <article class="card summary-card h-100">
                <div class="card-body">
                    <div class="summary-icon bg-<?= e($card['color']) ?>-subtle text-<?= e($card['color']) ?>">
                        <i class="bi <?= e($card['icon']) ?>"></i>
                    </div>
                    <div class="small text-secondary mt-3"><?= e($card['label']) ?></div>
                    <div class="h4 fw-bold mb-0 mt-1"><?= e($card['value']) ?></div>
                </div>
            </article>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <?php if ($attendanceToday !== null): ?>
        <div class="col-12">
            <section class="card border-0 shadow-sm"><div class="card-body d-flex flex-wrap gap-2 align-items-center">
                <strong class="me-auto">Chấm công hôm nay</strong>
                <?php if (empty($attendanceToday['GioVao'])): ?>
                    <form method="post" action="<?= e(url('attendance-check-in')) ?>"><input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><button class="btn btn-success" type="submit">Chấm vào</button></form>
                <?php elseif (empty($attendanceToday['GioRa'])): ?>
                    <span class="text-secondary">Vào: <?= e($attendanceToday['GioVao']) ?></span>
                    <form method="post" action="<?= e(url('attendance-check-out')) ?>"><input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><button class="btn btn-primary" type="submit">Chấm ra</button></form>
                <?php else: ?>
                    <span class="badge text-bg-success">Vào <?= e($attendanceToday['GioVao']) ?> · Ra <?= e($attendanceToday['GioRa']) ?></span>
                <?php endif; ?>
            </div></section>
        </div>
    <?php endif; ?>
    <?php if ($pendingApprovals !== []): ?>
        <div class="col-12"><section class="card border-0 shadow-sm"><div class="card-body">
            <h2 class="h5">Chờ tôi phê duyệt</h2>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($pendingApprovals as $item): ?>
                    <a class="btn btn-outline-primary" href="<?= e(url($item['route'])) ?>"><?= e($item['label']) ?> <span class="badge text-bg-warning"><?= e($item['count']) ?></span></a>
                <?php endforeach; ?>
            </div>
        </div></section></div>
    <?php endif; ?>
    <?php if ($departments !== []): ?>
        <div class="col-12 col-xl-7">
            <section class="card dashboard-panel h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h2 class="h5 mb-1">Nhân sự theo phòng ban</h2>
                            <p class="small text-secondary mb-0">Không tính nhân viên đã nghỉ việc</p>
                        </div>
                        <i class="bi bi-bar-chart-line text-primary fs-4"></i>
                    </div>
                    <?php if ($departments !== []): ?>
                        <div class="chart-container">
                            <canvas id="departmentChart" aria-label="Biểu đồ nhân sự theo phòng ban" role="img"></canvas>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">Chưa có dữ liệu phòng ban để hiển thị.</div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    <?php endif; ?>
    <div class="col-12 <?= $departments !== [] ? 'col-xl-5' : '' ?>">
        <section class="card dashboard-panel h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 class="h5 mb-1"><?= $personal ? 'Thông báo của bạn' : 'Thông báo nhân sự' ?></h2>
                        <p class="small text-secondary mb-0">Thông tin cần theo dõi và xử lý</p>
                    </div>
                    <i class="bi bi-bell text-primary fs-4"></i>
                </div>
                <?php if ($notifications === []): ?>
                    <div class="empty-state">Hiện chưa có thông báo.</div>
                <?php else: ?>
                    <div class="notification-list">
                        <?php foreach ($notifications as $notification): ?>
                            <article class="notification-item">
                                <span class="notification-dot bg-<?= e($notification['type']) ?>"></span>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between gap-2">
                                        <span class="badge bg-<?= e($notification['type']) ?>-subtle text-<?= e($notification['type']) ?>">
                                            <?= e($notification['category']) ?>
                                        </span>
                                        <?php if (!empty($notification['date'])): ?>
                                            <time class="small text-secondary text-nowrap" datetime="<?= e($notification['date']) ?>">
                                                <?= e(date('d/m/Y', strtotime((string) $notification['date']))) ?>
                                            </time>
                                        <?php endif; ?>
                                    </div>
                                    <div class="fw-semibold mt-1"><?= e($notification['title']) ?></div>
                                    <div class="small text-secondary"><?= e($notification['detail']) ?></div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<?php if ($departments !== []): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        const departmentLabels = <?= json_encode($departmentLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const departmentCounts = <?= json_encode($departmentCounts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        new Chart(document.getElementById('departmentChart'), {
            type: 'bar',
            data: {
                labels: departmentLabels,
                datasets: [{
                    label: 'Số nhân viên',
                    data: departmentCounts,
                    backgroundColor: '#2c7fc1',
                    borderRadius: 6,
                    maxBarThickness: 54
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                    x: { grid: { display: false } }
                }
            }
        });
    </script>
<?php endif; ?>
