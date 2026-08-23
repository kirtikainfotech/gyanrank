<?php
if (!function_exists('gr_setup_e')) {
    function gr_setup_e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$settings = isset($settings) && is_array($settings) ? $settings : array();
$template = isset($template) ? $template : ($settings['template_type'] ?? 'school');
$templateTitle = $template === 'degree_college' ? 'Degree College ERP' : ($template === 'coaching' ? 'Institute / Coaching ERP' : 'School / College ERP');
$degree_programs = isset($degree_programs) && is_array($degree_programs) ? $degree_programs : array();
$degree_terms = isset($degree_terms) && is_array($degree_terms) ? $degree_terms : array();
$degree_subjects = isset($degree_subjects) && is_array($degree_subjects) ? $degree_subjects : array();
$degree_fees = isset($degree_fees) && is_array($degree_fees) ? $degree_fees : array();
$coaching_courses = isset($coaching_courses) && is_array($coaching_courses) ? $coaching_courses : array();
$coaching_batches = isset($coaching_batches) && is_array($coaching_batches) ? $coaching_batches : array();
$coaching_fees = isset($coaching_fees) && is_array($coaching_fees) ? $coaching_fees : array();

$programNames = array();
foreach ($degree_programs as $program) {
    $programNames[(int) $program['id']] = $program['program_name'];
}
$termsByProgram = array();
$termNames = array();
foreach ($degree_terms as $term) {
    $termsByProgram[(int) $term['program_id']][] = $term;
    $termNames[(int) $term['id']] = $term['term_name'];
}
$courseNames = array();
foreach ($coaching_courses as $course) {
    $courseNames[(int) $course['id']] = $course['course_name'];
}
?>
<style>
    .gr-setup-summary {display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:10px}
    .gr-setup-stat {border:1px solid #d9e5ef;border-top:3px solid #ff8900;background:#fff;padding:10px 12px;min-height:64px}
    .gr-setup-stat b {display:block;color:#003c68;font-size:18px;line-height:22px}
    .gr-setup-stat span {color:#6f7d8b;font-size:12px}
    .gr-setup-form-grid {display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
    .gr-setup-form-grid .wide {grid-column:span 2}
    .gr-setup-box {border-top-color:#ff8900}
    .gr-setup-table>thead>tr>th {background:#edf5fb;color:#003c68;text-transform:uppercase;font-size:12px;letter-spacing:.2px}
    .gr-setup-table>tbody>tr>td {vertical-align:middle}
    .gr-setup-badge {display:inline-block;padding:3px 9px;border-radius:14px;background:#e6f7f0;color:#00a65a;font-weight:700;font-size:12px}
    .gr-setup-muted {color:#6f7d8b;font-size:12px}
    @media (max-width: 991px) {
        .gr-setup-summary {grid-template-columns:1fr}
        .gr-setup-form-grid {grid-template-columns:1fr}
        .gr-setup-form-grid .wide {grid-column:auto}
    }
</style>
<div class="content-wrapper">
    <section class="content-header">
        <h1>Gyan Nexa ERP Setup</h1>
    </section>
    <section class="content">
        <?php if ($this->session->flashdata('msg')) { ?>
            <?php echo $this->session->flashdata('msg'); ?>
        <?php } ?>
        <div class="gr-setup-summary">
            <div class="gr-setup-stat">
                <b><?php echo gr_setup_e($templateTitle); ?></b>
                <span>Current institute template</span>
            </div>
            <div class="gr-setup-stat">
                <b><?php echo gr_setup_e($settings['label_class'] ?? 'Class'); ?></b>
                <span>Main academic unit</span>
            </div>
            <div class="gr-setup-stat">
                <b><?php echo gr_setup_e($settings['label_section'] ?? 'Section'); ?></b>
                <span>Grouping / term unit</span>
            </div>
        </div>

        <?php if ($template === 'school') { ?>
            <div class="box box-primary gr-setup-box">
                <div class="box-header with-border">
                    <h3 class="box-title">School / College ERP Active</h3>
                </div>
                <div class="box-body">
                    <p class="text-muted">Is template me existing class, section, session, admission, fees, exam, transport, hostel aur library workflow unchanged rahega.</p>
                </div>
            </div>
        <?php } elseif ($template === 'degree_college') { ?>
            <div class="box box-primary gr-setup-box">
                <div class="box-header with-border">
                    <h3 class="box-title">Degree College Master Setup</h3>
                    <form method="post" class="pull-right">
                        <input type="hidden" name="action" value="sync_template_master">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-refresh"></i> Sync To Admission</button>
                    </form>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-6">
                            <form method="post">
                                <input type="hidden" name="action" value="add_degree_program">
                                <div class="gr-setup-form-grid">
                                    <div class="form-group">
                                        <label>Program Code</label>
                                        <input type="text" name="program_code" class="form-control" placeholder="BA">
                                    </div>
                                    <div class="form-group wide">
                                        <label>Program Name</label>
                                        <input type="text" name="program_name" class="form-control" placeholder="Bachelor of Arts">
                                    </div>
                                    <div class="form-group">
                                        <label>Years</label>
                                        <input type="number" name="duration_years" class="form-control" value="3" min="1" max="6">
                                    </div>
                                    <div class="form-group">
                                        <label>Pattern</label>
                                        <select name="academic_pattern" class="form-control">
                                            <option value="yearly">Yearly</option>
                                            <option value="semester">Semester</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Fee Mode</label>
                                        <select name="fee_mode" class="form-control">
                                            <option value="yearly">Yearly</option>
                                            <option value="semester">Semester</option>
                                            <option value="full_course">Full Course</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <button type="submit" class="btn btn-primary btn-block">Add Program</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <form method="post">
                                <input type="hidden" name="action" value="add_degree_subject">
                                <div class="gr-setup-form-grid">
                                    <div class="form-group">
                                        <label>Program</label>
                                        <select name="program_id" class="form-control">
                                            <?php foreach ($degree_programs as $program) { ?>
                                                <option value="<?php echo (int) $program['id']; ?>"><?php echo gr_setup_e($program['program_name']); ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Term</label>
                                        <select name="term_id" class="form-control">
                                            <option value="">Common</option>
                                            <?php foreach ($degree_terms as $term) { ?>
                                                <option value="<?php echo (int) $term['id']; ?>"><?php echo gr_setup_e(($programNames[(int) $term['program_id']] ?? 'Program') . ' - ' . $term['term_name']); ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Subject Code</label>
                                        <input type="text" name="subject_code" class="form-control" placeholder="BA-HIS">
                                    </div>
                                    <div class="form-group">
                                        <label>Subject Name</label>
                                        <input type="text" name="subject_name" class="form-control" placeholder="History">
                                    </div>
                                    <div class="form-group">
                                        <label>Type</label>
                                        <select name="subject_type" class="form-control">
                                            <option value="core">Core</option>
                                            <option value="elective">Elective</option>
                                            <option value="optional">Optional</option>
                                            <option value="practical">Practical</option>
                                            <option value="project">Project</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <button type="submit" class="btn btn-primary btn-block">Add Subject</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <hr>
                    <form method="post">
                        <input type="hidden" name="action" value="add_degree_fee">
                        <div class="gr-setup-form-grid">
                            <div class="form-group">
                                <label>Program</label>
                                <select name="program_id" class="form-control">
                                    <?php foreach ($degree_programs as $program) { ?>
                                        <option value="<?php echo (int) $program['id']; ?>"><?php echo gr_setup_e($program['program_name']); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Term</label>
                                <select name="term_id" class="form-control">
                                    <option value="">Full Program</option>
                                    <?php foreach ($degree_terms as $term) { ?>
                                        <option value="<?php echo (int) $term['id']; ?>"><?php echo gr_setup_e(($programNames[(int) $term['program_id']] ?? 'Program') . ' - ' . $term['term_name']); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Fee Mode</label>
                                <select name="fee_mode" class="form-control">
                                    <option value="yearly">Yearly</option>
                                    <option value="semester">Semester</option>
                                    <option value="full_course">Full Course</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Fee Title</label>
                                <input type="text" name="fee_title" class="form-control" placeholder="Tuition Fee">
                            </div>
                            <div class="form-group">
                                <label>Amount</label>
                                <input type="number" step="0.01" name="amount" class="form-control" placeholder="15000">
                            </div>
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="submit" class="btn btn-primary btn-block">Add Fee</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="box box-primary gr-setup-box">
                <div class="box-header with-border">
                    <h3 class="box-title">Degree College Data</h3>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover gr-setup-table">
                        <thead><tr><th>Program</th><th>Pattern</th><th>Terms</th><th>Fee Mode</th><th>Subjects</th><th>Fees</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($degree_programs as $program) {
                                $pid = (int) $program['id'];
                                $subjectCount = 0;
                                $feeCount = 0;
                                foreach ($degree_subjects as $subject) {
                                    if ((int) $subject['program_id'] === $pid) {
                                        $subjectCount++;
                                    }
                                }
                                foreach ($degree_fees as $fee) {
                                    if ((int) $fee['program_id'] === $pid) {
                                        $feeCount++;
                                    }
                                }
                            ?>
                                <tr>
                                    <td><b><?php echo gr_setup_e($program['program_code']); ?></b><br><span class="gr-setup-muted"><?php echo gr_setup_e($program['program_name']); ?></span></td>
                                    <td><?php echo gr_setup_e(ucfirst($program['academic_pattern'])); ?></td>
                                    <td><?php echo (int) $program['total_terms']; ?></td>
                                    <td><?php echo gr_setup_e(str_replace('_', ' ', $program['fee_mode'])); ?></td>
                                    <td><?php echo (int) $subjectCount; ?></td>
                                    <td><?php echo (int) $feeCount; ?></td>
                                    <td><span class="gr-setup-badge">Active</span></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php } elseif ($template === 'coaching') { ?>
            <div class="box box-primary gr-setup-box">
                <div class="box-header with-border">
                    <h3 class="box-title">Institute / Coaching Master Setup</h3>
                    <form method="post" class="pull-right">
                        <input type="hidden" name="action" value="sync_template_master">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-refresh"></i> Sync To Admission</button>
                    </form>
                </div>
                <div class="box-body">
                    <form method="post">
                        <input type="hidden" name="action" value="add_coaching_course">
                        <div class="gr-setup-form-grid">
                            <div class="form-group">
                                <label>Course Code</label>
                                <input type="text" name="course_code" class="form-control" placeholder="BASIC-3M">
                            </div>
                            <div class="form-group wide">
                                <label>Course Name</label>
                                <input type="text" name="course_name" class="form-control" placeholder="Basic Computer Course">
                            </div>
                            <div class="form-group">
                                <label>Duration Months</label>
                                <input type="number" name="duration_months" class="form-control" value="3" min="1" max="60">
                            </div>
                            <div class="form-group">
                                <label>Fee Mode</label>
                                <select name="fee_mode" class="form-control">
                                    <option value="full_course">Full Course</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="installment">Installment</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Course Fee</label>
                                <input type="number" step="0.01" name="course_fee" class="form-control" placeholder="4999">
                            </div>
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="submit" class="btn btn-primary btn-block">Add Course</button>
                            </div>
                        </div>
                    </form>
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <form method="post">
                                <input type="hidden" name="action" value="add_coaching_batch">
                                <div class="gr-setup-form-grid">
                                    <div class="form-group wide">
                                        <label>Course</label>
                                        <select name="course_id" class="form-control">
                                            <?php foreach ($coaching_courses as $course) { ?>
                                                <option value="<?php echo (int) $course['id']; ?>"><?php echo gr_setup_e($course['course_name']); ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div class="form-group wide">
                                        <label>Batch Name</label>
                                        <input type="text" name="batch_name" class="form-control" placeholder="Morning Batch">
                                    </div>
                                    <div class="form-group">
                                        <label>Start Date</label>
                                        <input type="date" name="start_date" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label>End Date</label>
                                        <input type="date" name="end_date" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label>Timing</label>
                                        <input type="text" name="timing" class="form-control" placeholder="08:00 - 09:00">
                                    </div>
                                    <div class="form-group">
                                        <label>Capacity</label>
                                        <input type="number" name="capacity" class="form-control" placeholder="30">
                                    </div>
                                    <div class="form-group wide">
                                        <label>Trainer</label>
                                        <input type="text" name="trainer_name" class="form-control" placeholder="Instructor name">
                                    </div>
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <button type="submit" class="btn btn-primary btn-block">Add Batch</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <form method="post">
                                <input type="hidden" name="action" value="add_coaching_fee">
                                <div class="gr-setup-form-grid">
                                    <div class="form-group wide">
                                        <label>Course</label>
                                        <select name="course_id" class="form-control">
                                            <?php foreach ($coaching_courses as $course) { ?>
                                                <option value="<?php echo (int) $course['id']; ?>"><?php echo gr_setup_e($course['course_name']); ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div class="form-group wide">
                                        <label>Fee Title</label>
                                        <input type="text" name="fee_title" class="form-control" placeholder="Course Fee">
                                    </div>
                                    <div class="form-group">
                                        <label>Fee Mode</label>
                                        <select name="fee_mode" class="form-control">
                                            <option value="full_course">Full Course</option>
                                            <option value="monthly">Monthly</option>
                                            <option value="installment">Installment</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Installments</label>
                                        <input type="number" name="installment_count" class="form-control" value="1" min="1" max="24">
                                    </div>
                                    <div class="form-group">
                                        <label>Amount</label>
                                        <input type="number" step="0.01" name="amount" class="form-control" placeholder="4999">
                                    </div>
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <button type="submit" class="btn btn-primary btn-block">Add Fee</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="box box-primary gr-setup-box">
                <div class="box-header with-border">
                    <h3 class="box-title">Coaching Data</h3>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover gr-setup-table">
                        <thead><tr><th>Course</th><th>Duration</th><th>Fee Mode</th><th>Fee</th><th>Batches</th><th>Fee Plans</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($coaching_courses as $course) {
                                $cid = (int) $course['id'];
                                $batchCount = 0;
                                $feeCount = 0;
                                foreach ($coaching_batches as $batch) {
                                    if ((int) $batch['course_id'] === $cid) {
                                        $batchCount++;
                                    }
                                }
                                foreach ($coaching_fees as $fee) {
                                    if ((int) $fee['course_id'] === $cid) {
                                        $feeCount++;
                                    }
                                }
                            ?>
                                <tr>
                                    <td><b><?php echo gr_setup_e($course['course_code']); ?></b><br><span class="gr-setup-muted"><?php echo gr_setup_e($course['course_name']); ?></span></td>
                                    <td><?php echo (int) $course['duration_months']; ?> months</td>
                                    <td><?php echo gr_setup_e(str_replace('_', ' ', $course['fee_mode'])); ?></td>
                                    <td>Rs <?php echo gr_setup_e(number_format((float) $course['course_fee'], 2)); ?></td>
                                    <td><?php echo (int) $batchCount; ?></td>
                                    <td><?php echo (int) $feeCount; ?></td>
                                    <td><span class="gr-setup-badge">Active</span></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php } ?>
    </section>
</div>
