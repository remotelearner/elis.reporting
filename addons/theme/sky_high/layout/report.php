<?php

$hassidepre = (empty($PAGE->layout_options['noblocks']) && $PAGE->blocks->region_has_content('side-pre', $OUTPUT));
$hasnavbar = (empty($PAGE->layout_options['nonavbar']) && $PAGE->has_navbar());
$showsidepre = ($hassidepre && !$PAGE->blocks->region_completely_docked('side-pre', $OUTPUT));

$custommenu = $OUTPUT->custom_menu();
$hascustommenu = (empty($PAGE->layout_options['nocustommenu']) && !empty($custommenu));

$bodyclasses = array();
if (!$showsidepre) {
    $bodyclasses[] = 'content-only';
}
if ($hassidepre) {
    $bodyclasses[] = 'background';
}

if (!empty($PAGE->theme->settings->logo)) {
    $logourl = $PAGE->theme->settings->logo;
} else {
    $logourl = NULL;
}

if (!empty($PAGE->theme->settings->footnote)) {
    $footnote = $PAGE->theme->settings->footnote;
} else {
    $footnote = '<!-- There was no custom footnote set -->';
}

echo $OUTPUT->doctype() ?>
<html <?php echo $OUTPUT->htmlattributes() ?>>
<head>
    <title><?php echo $PAGE->title ?></title>
    <link rel="shortcut icon" href="<?php echo $OUTPUT->pix_url('favicon', 'theme')?>" />
    <meta name="description" content="<?php p(strip_tags(format_text($SITE->summary, FORMAT_HTML))) ?>" />
    <?php echo $OUTPUT->standard_head_html() ?>
</head>
<body id="<?php p($PAGE->bodyid) ?>" class="<?php p($PAGE->bodyclasses.' '.join(' ', $bodyclasses)) ?>">
<?php echo $OUTPUT->standard_top_of_body_html() ?>

<div id="page">
    <div id="wrapper" class="clearfix">

<!-- START OF HEADER -->

    <div id="page-header" class="clearfix">
        <div id="page-header-wrapper">
            <?php if($logourl == NULL) { ?>
             <h1 class="headermain">
                <?php echo $PAGE->heading ?>
            </h1>
            <?php } else { ?>
           <img class="logo" src="<?php echo $logourl;?>" alt="Custom logo here" /><h1 class="headerwlogo">- <?php echo $PAGE->heading ?></h1>
            <?php } ?>


            <div class="headermenu">
                <?php
                        echo $OUTPUT->login_info();
                           if (!empty($PAGE->layout_options['langmenu'])) {
                               echo $OUTPUT->lang_menu();
                        }
                           echo $PAGE->headingmenu
                    ?>
            </div>
        </div>
    </div>

<!-- END OF HEADER -->

<!-- START OF CONTENT -->
<?php if ($hascustommenu) { ?>
      <div id="custommenu"><?php echo $custommenu; ?></div>
<?php } ?>
<div class="navbar clearfix">
            <?php if ($hasnavbar) { ?>
            <div class="breadcrumb"><?php echo $OUTPUT->navbar(); ?></div>
            <div class="navbutton"> <?php echo $PAGE->button; ?></div>
            <?php } ?>
      </div>




<div id="page-content-wrapper">
    <div id="page-content">
        <div id="region-main-box">
            <div id="region-post-box">

                <div id="region-main-wrap">
                    <div id="region-main">
                        <div class="region-content">
                            <?php echo core_renderer::MAIN_CONTENT_TOKEN ?>
                        </div>
                    </div>
                </div>

                <?php if ($hassidepre) { ?>
                <div id="region-pre" class="block-region">
                    <div class="region-content">
                        <?php echo $OUTPUT->blocks_for_region('side-pre') ?>
                    </div>
                </div>
                <?php } ?>

            </div>
        </div>
    </div>
</div>

<!-- END OF CONTENT -->



<!-- START OF FOOTER -->

    <div id="page-footer">
        <div class="footnote"><?php echo $footnote; ?></div>
        <?php
        echo $OUTPUT->login_info();
        echo $OUTPUT->standard_footer_html();
        ?>
    </div>

<!-- END OF FOOTER -->
</div>
</div>
    <p class="helplink">
        <?php echo page_doc_link(get_string('moodledocslink')) ?>
    </p><center>
        <?php
    echo $OUTPUT->home_link();
    echo $OUTPUT->standard_end_of_body_html() ?>
</center>
</body>
</html>
