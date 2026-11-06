<?php

namespace Apachish\WpRepo\Support;

use Apachish\WpRepo\Contracts\EntitlementChecker;
use Apachish\WpRepo\Models\WpPackage;
use Apachish\WpRepo\Models\WpSite;

class AllowAllEntitlement implements EntitlementChecker
{
    public function allows(WpSite $site, WpPackage $package): bool
    {
        return true;
    }
}
