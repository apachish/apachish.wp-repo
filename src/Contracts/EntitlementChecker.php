<?php

namespace Apachish\WpRepo\Contracts;

use Apachish\WpRepo\Models\WpPackage;
use Apachish\WpRepo\Models\WpSite;

interface EntitlementChecker
{
    /** Decide whether $site may receive the (paid) $package. */
    public function allows(WpSite $site, WpPackage $package): bool;
}
