<?php

namespace hexa_app_publish\Publishing\Sites\Services;

use hexa_app_publish\Publishing\Sites\Models\PublishSite;
use hexa_package_whm\Models\HostingAccount;
use hexa_package_whm\Models\WhmServer;

class PublishSiteWordPressTargetFactory
{
    /**
     * @return array<string, mixed>
     */
    public function fromSite(PublishSite $site): array
    {
        $account = $site->hosting_account_id ? HostingAccount::find($site->hosting_account_id) : null;
        $server = $account ? WhmServer::find($account->whm_server_id) : null;
        $isWpToolkit = (($site->connection_type ?? "wptoolkit") === "wptoolkit") && $server && $site->wordpress_install_id;

        return [
            "mode" => $isWpToolkit ? "wptoolkit" : "rest",
            "site_id" => (int) $site->id,
            "site_name" => (string) ($site->name ?? "WordPress site"),
            "url" => rtrim((string) ($site->url ?? ""), "/"),
            "username" => (string) ($site->wp_username ?? ""),
            "application_password" => (string) ($site->wp_application_password ?? ""),
            "server" => $server,
            "install_id" => $site->wordpress_install_id ? (int) $site->wordpress_install_id : null,
            "default_author" => (string) ($site->default_author ?? ""),
        ];
    }
}
