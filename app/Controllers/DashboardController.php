<?php
declare(strict_types=1);

final class DashboardController {
    public static function home(): void {
        $u = Auth::user();
        $title = "Dashboard";
        include BASE_PATH . '/ui/layout/header.php';
        include BASE_PATH . '/ui/dashboard/home.php';
        include BASE_PATH . '/ui/layout/footer.php';
    }
}
