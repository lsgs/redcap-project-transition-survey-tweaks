<?php
/**
 * REDCap External Module: Project Transition Survey Tweaks
 * Enhancements for surveys delivered when creating/requesting new projects, copying projects, and moving projects into Production status.
 * @author Luke Stevens, Murdoch Children's Research Institute
 * @author Peter Xiberras, Murdoch Children's Research Institute
 */
namespace MCRI\ProjectTransitionSurveyTweaks;

use ExternalModules\AbstractExternalModule;

class ProjectTransitionSurveyTweaks extends AbstractExternalModule
{
    public function redcap_every_page_top($project_id) {
        if ($this->isNotRequired()) return;
        global $Proj;
        $transitions = array();
        if (str_replace(APP_PATH_WEBROOT_PARENT, '', PAGE_FULL)=='index.php' && isset($_GET['action']) && $_GET['action']==='create') {
            $transitions[] = 'create_project'; // create or request a new project

        } else if (PAGE == 'ProjectGeneral/copy_project_form.php') {
            $transitions[] = 'copy_project'; // copy a project

        } else if (PAGE == 'ProjectSetup/index.php' && $Proj->project['status']==0) {
            $transitions[] = 'move_to_prod_status'; // move dev to production

        } else if (PAGE == 'ProjectSetup/other_functionality.php') {
            $transitions[] = 'mark_completed'; // mark project completed
            if ($Proj->project['status'] > 0) {
                $transitions[] = 'move_to_analysis_status'; // move from prod to analysis
            }
        }

        foreach ($transitions as $transition) {
            $surveyUrl = $this->getTransitionSurveyUrl($transition);
            if (!empty($surveyUrl) && method_exists($this, $transition)) {
                $this->$transition($surveyUrl);
            }
        }
    }

    /**
     * isNotRequired()
     * No user or current user is super user and system-level force option not enabled
     * @return bool
     */
    protected function isNotRequired() {
        if (!defined('USERID')) return true;
        $user = $this->getUser();
        if (!isset($user)) return true;
        $super = $user->isSuperUser();
        $force = 1==$this->getSystemSetting('force-superuser');
        return $super && !$force;
    }

    /**
     * getTransitionSurveyPid
     * Read the pid for the specified transition and get the corresponding public survey URL.
     */
    protected function getTransitionSurveyUrl($transition) {
        global $survey_pid_create_project, $survey_pid_mark_completed, $survey_pid_move_to_analysis_status, $survey_pid_move_to_prod_status;
        $transitionSurveyVar = ($transition==='copy_project') ? "survey_pid_create_project" : "survey_pid_$transition"; // copy project transition uses create project survey
        $pid = $$transitionSurveyVar;
        if (empty($pid)) return null;

        $hash = '';
        $r = $this->query('select hash from redcap_surveys_participants sp inner join redcap_surveys s on sp.survey_id=s.survey_id where project_id=? and participant_email is null limit 1', [ $pid ]);
        while ($row = $r->fetch_assoc()) {
            $hash = $row['hash'];
        }
        if (empty($hash)) return null;
        $user = (defined('USERID')) ? USERID : '';
        $thisPid = (defined('PROJECT_ID') && $transition!=='copy_project') ? PROJECT_ID : ''; // project_id when copying gets set to pid of copy
        return APP_PATH_SURVEY_FULL."?s=$hash&username=$user&project_id=$thisPid";
    }

    /**
     * create_project
     * Add JavaScript to "Create/Request New Project" page to add entered values to "New Project" survey query string.
     */
    protected function create_project($surveyUrl) {
        ?>
        <script type='text/javascript'>
            $(document).ready(function() {
                /* Project Transition Survey Tweaks JavaScript */
                $('form[name=createdb]').find('button.btn-primaryrc').eq(0).removeAttr('onclick');
                $('form[name=createdb]').find('button.btn-primaryrc').eq(0).on('click', function() {
                    if ($('#currenttitle').val() == $('#app_title').val()) {
						simpleDialog('<?=\RCView::tt('copy_project_11')?>'); // Please change the title of the new project so that it is different from the original.
						return false;
					}

                    var surveyUrl = '<?=$surveyUrl?>';
                    var qs = name = value = '';
                    $('form[name=createdb]').find('.x-form-field').each(function(){
                        // inputs/selects/textareas from form: add key/value pairs to query string
                        name = $(this).attr('name');
                        value = $(this).val();
                        if (value.trim()!=='') {
                            qs += '&'+name+'='+encodeURIComponent(value);
                        }
                    });
                    $('input[type=checkbox][name^=purpose_other]').each(function(){
                        // research type checkboxes
                        name = $(this).attr('name').replace('purpose_other[','purpose_research___').replace(']',''); // e.g. purpose_other[0] -> purpose_research___0
                        if ($(this).is(':checked')) {
                            qs += '&'+name+'=1';
                        }
                    });
                    $('input[name=project_template_radio]:checked, input[name=copyof]:checked').each(function(){
                        // template option, template used
                        name = $(this).attr('name');
                        value = $(this).val();
                        qs += '&'+name+'='+value;
                    });

					if (setFieldsCreateFormChk()) { 
                        showProgress(1);
                        openSurveyDialogIframe(surveyUrl+qs);
                    }; 
                    return false;
                });
            });
        </script>
        <?php
    }

    /**
     * copy_project
     * Add JavaScript to "Copy Project" page to add entered values to "New Project" survey query string.
     */
    protected function copy_project($surveyUrl) { 
        ?>
        <script type='text/javascript'>
            $(document).ready(function() {
                /* Project Transition Survey Tweaks JavaScript */
                $('form[name=createdb]').find('button.btn-rcgreen').eq(0).removeAttr('onclick');
                $('form[name=createdb]').find('button.btn-rcgreen').eq(0).on('click', function() {
                    //console.log("clicked");
                    var surveyUrl = '<?=$surveyUrl?>&project_template_radio=3&copyof='+pid;
                    var qs = name = value = '';
                    $('form[name=createdb]').find('.x-form-field').each(function(){
                        // inputs/selects/textareas from form: add key/value pairs to query string
                        name = $(this).attr('name');
                        value = $(this).val();
                        if (value.trim()!=='') {
                            qs += '&'+name+'='+encodeURIComponent(value);
                        }
                    });
                    $('input[type=checkbox][name^=purpose_other]').each(function(){
                        // research type checkboxes
                        name = $(this).attr('name').replace('purpose_other[','purpose_research___').replace(']',''); // e.g. purpose_other[0] -> purpose_research___0
                        if ($(this).is(':checked')) {
                            qs += '&'+name+'=1';
                        }
                    });
                    $('input[type=checkbox][name^=copy_]').each(function(){
                        // copy what checkboxes
                        name = 'prop___'+$(this).attr('name'); // e.g. copy_records -> prop___copy_records
                        if ($(this).is(':checked')) {
                            qs += '&'+name+'=1';
                        }
                    });

                    if (setFieldsCreateFormChk()) { 
                        showProgress(1); 
                        console.log(surveyUrl+qs);
                        openSurveyDialogIframe(surveyUrl+qs);
                    }; 
                    return false;
                });
            });
        </script>
        <?php
    }

    /**
     * mark_completed
     * Add JavaScript to "Other Functionality" page to add entered values to "Mark Completed" survey query string.
     */
    protected function mark_completed($surveyUrl) { 
        ?>
        <script type='text/javascript'>
            $(document).ready(function() {
                /* 
                    Project Transition Survey Tweaks JavaScript 
                    Override the built-in survey launcher passing our survey link with additional parameters project_id and userid
                */
                var defaultOpenSurveyDialogIframe = openSurveyDialogIframe;
                openSurveyDialogIframe = function() {
                    defaultOpenSurveyDialogIframe('<?=$surveyUrl?>');
                }
            });
        </script>
        <?php
    }
    
    /**
     * move_to_analysis_status
     * Add JavaScript to "Other Functionality" page to add entered values to "Move to Analysis" survey query string.
     */
    protected function move_to_analysis_status($surveyUrl) { 
        ?>
        <script type='text/javascript'>
            $(document).ready(function() {
                /* 
                    Project Transition Survey Tweaks JavaScript 
                    Override the built-in survey launcher passing our survey link with additional parameters project_id and userid
                */
                var defaultOpenSurveyDialogIframe = openSurveyDialogIframe;
                openSurveyDialogIframe = function() {
                    defaultOpenSurveyDialogIframe('<?=$surveyUrl?>');
                }
            });
        </script>
        <?php
    }
    
    /**
     * move_to_prod_status
     * Add JavaScript to "Project Setup" page to add entered values to survey query string.
     */
    protected function move_to_prod_status($surveyUrl) { 
        ?>
        <script type='text/javascript'>
            $(document).ready(function() {
                /* 
                    Project Transition Survey Tweaks JavaScript 
                    Override the built-in survey launcher passing our survey link with additional parameters project_id and userid
                */
                var defaultOpenSurveyDialogIframe = openSurveyDialogIframe;
                openSurveyDialogIframe = function() {
                    defaultOpenSurveyDialogIframe('<?=$surveyUrl?>');
                }
            });
        </script>
        <?php
    }
}