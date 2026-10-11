<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Models\Reports;
use DateTimeImmutable;

final class ReportsController extends Controller
{
    public function index(): void
    {
        $this->authorizeReports();
        [$type, $from, $to, $department] = $this->filters();
        $employeeId = Auth::can('reports', 'view') ? null : Auth::user()['MaNV'];
        $model = new Reports(Database::connection());
        $result = $model->report($type, $from, $to, $department, $employeeId);
        $this->render('reports/index', [
            'title' => 'Báo cáo nhân sự',
            'reportTypes' => Reports::reportTypes(),
            'type' => $type,
            'from' => $from,
            'to' => $to,
            'department' => $department,
            'departments' => $model->departments(),
            'report' => $result,
            'personal' => $employeeId !== null,
        ]);
    }

    public function exportCsv(): void
    {
        $this->authorizeReports();
        [$type, $from, $to, $department] = $this->filters();
        $employeeId = Auth::can('reports', 'view') ? null : Auth::user()['MaNV'];
        $report = (new Reports(Database::connection()))->report($type, $from, $to, $department, $employeeId);
        $filename = 'bao-cao-' . $type . '-' . $from . '-' . $to . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        $output = fopen('php://output', 'wb');
        if ($output === false) {
            throw new \RuntimeException('Không thể tạo tệp báo cáo.');
        }
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, [$report['title']]);
        fputcsv($output, ['Từ ngày', $from, 'Đến ngày', $to, 'Phòng ban', $department]);
        fputcsv($output, $report['headers']);
        foreach ($report['rows'] as $row) {
            fputcsv($output, array_map(
                static fn (mixed $value): mixed => is_string($value) && preg_match('/^[=+\-@]/', $value) === 1
                    ? "'" . $value
                    : $value,
                $row
            ));
        }
        fclose($output);
    }

    private function authorizeReports(): void
    {
        if (Auth::can('reports', 'view')) {
            $this->authorize('reports', 'view');
        } else {
            $this->authorize('reports', 'personal');
        }
    }

    /** @return array{string,string,string,string} */
    private function filters(): array
    {
        $types = array_column(Reports::reportTypes(), 'key');
        $type = trim((string) ($_GET['type'] ?? 'employees'));
        $from = trim((string) ($_GET['from'] ?? date('Y-m-01')));
        $to = trim((string) ($_GET['to'] ?? date('Y-m-d')));
        $department = trim((string) ($_GET['department'] ?? ''));

        if (!in_array($type, $types, true) || !$this->validDate($from) || !$this->validDate($to) || $from > $to) {
            http_response_code(400);
            flash('error', 'Bộ lọc báo cáo không hợp lệ.');
            $this->redirect('reports');
        }
        if (Auth::can('reports', 'view')) {
            $validDepartment = $department === '' || in_array(
                $department,
                array_column((new Reports(Database::connection()))->departments(), 'MaPB'),
                true
            );
            if (!$validDepartment) {
                http_response_code(400);
                flash('error', 'Phòng ban được chọn không hợp lệ.');
                $this->redirect('reports');
            }
        } else {
            $department = '';
        }

        return [$type, $from, $to, $department];
    }

    private function validDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
