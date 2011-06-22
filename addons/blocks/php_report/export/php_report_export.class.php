<?php

abstract class php_report_export {

    abstract function export($query, $storage_path, $filename);

}

?>