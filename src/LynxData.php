<?php namespace ProcessWire;

require_once __DIR__ . '/LynxProfileData.php';
require_once __DIR__ . '/LynxLinkData.php';
require_once __DIR__ . '/LynxMediaData.php';
require_once __DIR__ . '/LynxBlockData.php';
require_once __DIR__ . '/LynxDataSanitization.php';
require_once __DIR__ . '/LynxAnalyticsData.php';
require_once __DIR__ . '/LynxDataSupport.php';

/** Data-layer composition facade retained for the Lynx module shell. */
trait LynxData {

    use LynxProfileData;
    use LynxLinkData;
    use LynxMediaData;
    use LynxBlockData;
    use LynxDataSanitization;
    use LynxAnalyticsData;
    use LynxDataSupport;
}
