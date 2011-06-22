<?php
$block_php_report_capabilities = array(

        'block/php_report:view' => array(

            'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
            'captype' => 'write',
            'contextlevel' => CONTEXT_SYSTEM,
            'legacy' => array(
                'admin' => CAP_ALLOW
            )
        ),

        'block/php_report:schedule' => array(

            'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
            'captype' => 'write',
            'contextlevel' => CONTEXT_SYSTEM,
            'legacy' => array(
                'admin' => CAP_ALLOW
            )
        ),

        'block/php_report:manageschedules' => array(

            'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
            'captype' => 'write',
            'contextlevel' => CONTEXT_SYSTEM,
            'legacy' => array(
                'admin' => CAP_ALLOW
            )
        ),

    );
?>
