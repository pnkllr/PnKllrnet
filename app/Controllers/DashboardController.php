<?php
declare(strict_types=1);

final class DashboardController {
    public static function home(): void {
        $u = Auth::user();
        $title = "Dashboard";
        
        // ▼ NEW: load tools & scopes for the view
        $tools = require BASE_PATH . '/app/Tools.php';
        require_once BASE_PATH . '/app/ToolsHelper.php';

        $grantedScopes = [];
        if ($u && !empty($u['id'])) {
            $grantedScopes = get_granted_scopes_for_user((int)$u['id']);
        }
        $hasAll = function(array $need, array $have): bool {
            return count(scopes_missing($need, $have)) === 0;
        };

        include BASE_PATH . '/ui/layout/header.php';
        include BASE_PATH . '/ui/dashboard/home.php';
        include BASE_PATH . '/ui/layout/footer.php';
    }
}
