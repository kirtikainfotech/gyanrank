<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <link rel="icon" type="image/png" href="assets/img/s-favican.png">
        <meta http-equiv="X-UA-Compatible" content="" />
        <meta content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0' name='viewport' />
        <meta name="theme-color" content="" />
        <style type="text/css">
            *{padding: 0; margin:0; box-sizing: border-box;}
            body{font-family: Arial, Helvetica, sans-serif; color: #26384d; font-size: 14px; line-height: 1.35;}
            .tableone{}
            .tableone td{padding:5px 10px}
            table.denifittable  {border: 1px solid #b9c9d7;border-collapse: collapse; background: rgba(255,255,255,.96);}
            .denifittable th {padding: 10px 10px; font-weight: bold; color:#06345f; background:#eef6fd; border-collapse: collapse;border-right: 1px solid #b9c9d7; border-bottom: 1px solid #b9c9d7;}
            .denifittable td {padding: 10px 10px; font-weight: bold;border-collapse: collapse;border-left: 1px solid #d4e0ea; color:#314357;}

            .mark-container{
                width: 1000px; min-height: 690px; position: relative; z-index: 2; margin: 0 auto; padding: 34px 52px;}

            .tcmybg {
                background:top center;
                background-size: 100% 100%;
                position: absolute;
                top: 0;
                left: 0;
                bottom: 0;
                z-index: 1;
            }
            .admit-logo-left {width: 150px; height: 58px; object-fit: contain;}
            .admit-logo-right {width: 78px; height: 58px; object-fit: contain;}
            .admit-heading {font-size: 24px; font-weight: bold; text-align: center; text-transform: uppercase; padding-top: 4px; color:#ffffff; letter-spacing:.3px;}
            .admit-title {font-size: 17px; font-weight: bold; text-align: center; text-transform: uppercase; color:#ffffff;}
            .admit-exam {display:inline-block; margin-top: 18px; padding: 6px 18px; border-radius: 999px; color:#06345f; background:#fff4e4; font-weight:bold; text-transform:uppercase; border:1px solid #ffd2a0;}
            .admit-field-label {color:#627184; font-size:12px; font-weight:bold; letter-spacing:.2px;}
            .admit-field-value {color:#26384d; font-weight:bold;}
            .admit-photo {width: 108px; height: 128px; object-fit: cover; border: 3px solid #ffffff; outline: 1px solid #b9c9d7; background:#f6f8fb;}
            .admit-sign {width: 150px; height: 54px; object-fit: contain;}

        </style>
    </head>
    <body>
        <?php
if ($admitcard->background_img != "") {
    ?>
            <img src="<?php echo base_url('uploads/admit_card/' . $admitcard->background_img); ?>" class="tcmybg" width="100%" height="100%" />
            <?php
}
?>

        <div class="mark-container">
            <table cellpadding="0" cellspacing="0" width="100%">
                <?php
if ($admitcard->title != "" || $admitcard->heading != "" || $admitcard->left_logo != "") {
    ?>
                    <tr>
                        <td valign="top">
                            <table cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td valign="top" align="left" width="170">
                                        <?php
if ($admitcard->left_logo != "") {
        ?>
                                            <img src="<?php echo base_url('uploads/admit_card/' . $admitcard->left_logo); ?>" class="admit-logo-left"/>
                                            <?php
}
    ?>
                                    </td>
                                    <td valign="top">
                                        <table cellpadding="0" cellspacing="0" width="100%">
                                            <?php
if ($admitcard->heading != "") {
        ?>
                                                <tr>
                                                    <td valign="top" class="admit-heading"><?php echo $admitcard->heading; ?></td>
                                                </tr>
                                                <?php
}
    ?>

                                            <tr><td valign="top" height="5"></td></tr>
                                            <?php
if ($admitcard->title != "") {
        ?>
                                                <tr>
                                                    <td valign="top" class="admit-title">
                                                        <?php echo $admitcard->title; ?></td>
                                                </tr>
                                                <?php
}
    ?>

                                        </table>
                                    </td>
                                    <td width="110" valign="top" align="right">
                                        <?php
if ($admitcard->right_logo != "") {
        ?>
                                            <img src="<?php echo base_url('uploads/admit_card/' . $admitcard->right_logo); ?>" class="admit-logo-right">
                                            <?php
}
    ?>
                                    </td>

                                </tr>
                            </table>
                        </td>
                    </tr>
                    <?php
}
?>

                <?php
if ($admitcard->exam_name) {
    ?>
                    <tr>
                        <td valign="top" style="text-align: center;"><span class="admit-exam"><?php echo $admitcard->exam_name; ?></span></td>
                    </tr>
                    <?php
}
?>

                <tr><td valign="top" height="10"></td></tr>
                <tr>
                    <td valign="top">
                        <table cellpadding="0" cellspacing="0" width="100%" style="text-transform: uppercase;">
                            <tr>
                                <td valign="top">
                                    <table cellpadding="0" cellspacing="0" width="100%" >
                                        <tr>
                                            <?php
if ($admitcard->is_roll_no) {
    ?>
                                                <td valign="top" width="25%" class="admit-field-label" style="padding-bottom: 10px;"> <?php echo $this->lang->line('roll_no') ?></td>
                                                <td valign="top" width="30%" class="admit-field-value" style="padding-bottom: 10px;">260101</td>
                                                <?php
}
?>
                                            <?php
if ($admitcard->is_admission_no) {
    ?>
                                                <td valign="top" width="20%" class="admit-field-label" style="padding-bottom: 10px;"> <?php echo $this->lang->line('admission_no') ?></td>
                                                <td valign="top" width="25%" class="admit-field-value" style="padding-bottom: 10px;">GR2026001</td>
                                                <?php
}
?>


                                        </tr>

                                        <tr>
                                            <?php
if ($admitcard->is_name) {
    ?>
                                                <td valign="top" class="admit-field-label" style="padding-bottom: 10px;"> <?php echo $this->lang->line('candidates') . " " . $this->lang->line('name') ?></td>
                                                <td valign="top" class="admit-field-value" style="text-transform: uppercase; padding-bottom: 10px;">Aarav Sharma</td>
                                                <?php
}
?>


                                            <?php
if ($admitcard->is_class || $admitcard->is_section) {
    ?>

                                                <td valign="top" class="admit-field-label" style="padding-bottom: 10px;"> <?php echo $this->lang->line('class'); ?></td>
                                                <td valign="top" class="admit-field-value" style="text-transform: uppercase; padding-bottom: 10px;">

                                                    <?php
if ($admitcard->is_class && $admitcard->is_section) {
        ?>
                                                        1 (A)
                                                        <?php
} elseif ($admitcard->is_class) {
        ?>
                                                        1
                                                        <?php
} elseif ($admitcard->is_section) {
        ?>
                                                        (A)
                                                        <?php
}
    ?>
                                                </td>



                                                <?php
}
?>
                                        </tr>

                                        <tr>
                                            <?php
if ($admitcard->is_dob) {
    ?>
                                                <td valign="top" class="admit-field-label" style="padding-bottom: 10px;"><?php echo $this->lang->line('d_o_b'); ?></td>
                                                <td valign="top" class="admit-field-value" style="text-transform: uppercase; padding-bottom: 10px;">14/08/2011</td>
                                                <?php
}
?>

                                            <?php
if ($admitcard->is_gender) {
    ?>
                                                <td valign="top" class="admit-field-label" style="padding-bottom: 10px;"> <?php echo $this->lang->line('gender'); ?></td>
                                                <td valign="top" class="admit-field-value" style="text-transform: uppercase; padding-bottom: 10px;"><?php echo $this->lang->line('male'); ?></td>
                                                <?php
}
?>

                                        </tr>

                                        <tr>
                                            <?php
if ($admitcard->is_father_name) {
    ?>
                                                <td valign="top" class="admit-field-label" style="padding-bottom: 10px;"><?php echo $this->lang->line('fathers') . " " . $this->lang->line('name') ?></td>
                                                <td valign="top" class="admit-field-value" style="text-transform: uppercase; padding-bottom: 10px;">Rajesh Sharma</td>
                                                <?php
}
?>

                                            <?php
if ($admitcard->is_mother_name) {
    ?>
                                                <td valign="top" class="admit-field-label" style="padding-bottom: 10px;"><?php echo $this->lang->line('mothers') . " " . $this->lang->line('name'); ?></td>
                                                <td valign="top" class="admit-field-value" style="text-transform: uppercase; padding-bottom: 10px;">Sunita Sharma</td>
                                                <?php
}
?>

                                        </tr>
                                        <tr>
                                            <?php
if ($admitcard->is_address) {
    ?>
                                                <td valign="top" class="admit-field-label" style="padding-bottom: 10px;"><?php echo $this->lang->line('address'); ?></td>
                                                <td colspan="3" valign="top" class="admit-field-value" style="text-transform: uppercase; padding-bottom: 10px;">24, Ashoka Road, Connaught Place, New Delhi 110001</td>
                                                <?php
}
?>

                                        </tr>
                                        <?php
if ($admitcard->school_name != "") {
    ?>
                                            <tr>
                                                <td valign="top" class="admit-field-label" style="padding-bottom: 10px;"><?php echo $this->lang->line('school_name') ?></td>
                                                <td valign="top" colspan="3" class="admit-field-value" style="text-transform: uppercase; padding-bottom: 10px;"><?php echo $admitcard->school_name; ?></td>
                                            </tr>
                                            <?php
}
?>

                                        <?php
if ($admitcard->exam_center != "") {
    ?>
                                            <tr>
                                                <td valign="top" class="admit-field-label" style="padding-bottom: 10px;"><?php echo $this->lang->line('exam') . " " . $this->lang->line('center'); ?></td>
                                                <td valign="top" colspan="3" class="admit-field-value" style="text-transform: uppercase; padding-bottom: 10px;"> <?php echo $admitcard->exam_center; ?></td>
                                            </tr>
                                            <?php
}
?>
                                    </table>
                                </td>
                                <?php
if ($admitcard->is_photo) {
    ?>
                                    <td valign="top" width="25%" align="right">
                                        <img src="<?php echo base_url('uploads/student_images/demo-aarav-sharma.svg'); ?>" class="admit-photo">
                                    </td>
                                    <?php
}
?>

                            </tr>
                        </table>
                    </td>
                </tr>
                <tr><td valign="top" height="10"></td></tr>
                <tr>
                    <td valign="top">
                        <table cellpadding="0" cellspacing="0" width="100%" class="denifittable">
                            <tr>
                                <th valign="top" style="text-align: center; text-transform: uppercase;"><?php echo $this->lang->line('theory_exam_date_time'); ?></th>
                                <th valign="top" style="text-align: center; text-transform: uppercase;"><?php echo $this->lang->line('paper_code') ?></th>
                                <th valign="top" style="text-align: center; text-transform: uppercase;"><?php echo $this->lang->line('subject'); ?></th>
                                <th valign="top" style="text-align: center; text-transform: uppercase;"><?php echo $this->lang->line('obted_by_student') ?></th>
                            </tr>
                            <tr>
                                <td valign="top" style="text-align: center;">03-Jun-2026 09:30 A.M. - 12:30 P.M.</td>
                                <td style="text-align: center;text-transform: uppercase;">MATH-10</td>
                                <td style="text-align: center;text-transform: uppercase;">Mathematics</td>
                                <td style="text-align: center;text-transform: uppercase;">TH</td>
                            </tr>
                            <tr>
                                <td valign="top" style="text-align: center;">06-Jun-2026 09:30 A.M. - 12:30 P.M.</td>
                                <td style="text-align: center;text-transform: uppercase;">SCI-10</td>
                                <td style="text-align: center;text-transform: uppercase;">Science</td>
                                <td style="text-align: center;text-transform: uppercase;">TH</td>
                            </tr>
                            <tr>
                                <td valign="top" style="text-align: center;">10-Jun-2026 09:30 A.M. - 12:30 P.M.</td>
                                <td style="text-align: center;text-transform: uppercase;">ENG-10</td>
                                <td style="text-align: center;text-transform: uppercase;">English Language</td>
                                <td style="text-align: center;text-transform: uppercase;">TH</td>
                            </tr>
                            <tr>
                                <td valign="top" style="text-align: center;">14-Jun-2026 09:30 A.M. - 12:30 P.M.</td>
                                <td style="text-align: center;text-transform: uppercase;">SST-10</td>
                                <td style="text-align: center;text-transform: uppercase;">Social Science</td>
                                <td style="text-align: center;text-transform: uppercase;">TH</td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr><td valign="top" height="5"></td></tr>

                <?php
if ($admitcard->content_footer != "") {
    ?>
                    <tr>
                        <td valign="top" style="padding-bottom: 15px; line-height: normal;"> <?php echo htmlspecialchars_decode($admitcard->content_footer); ?></td>
                    </tr>
                    <?php
}
?>
                <tr><td valign="top" height="20px"></td></tr>
                <?php
if ($admitcard->sign != "") {
    ?>
                    <tr>
                        <td align="right" valign="top">
                            <table cellpadding="0" cellspacing="0" width="100%" style="text-align: center;">
                                <tr>
                                    <td valign="top">
                                        <img src="<?php echo base_url('uploads/admit_card/' . $admitcard->sign); ?>" class="admit-sign"  />

                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <?php
}
?>
            </table>
        </div>
    </body>
</html>
