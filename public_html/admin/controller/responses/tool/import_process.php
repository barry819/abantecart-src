<?php
/*------------------------------------------------------------------------------
  $Id$

  AbanteCart, Ideal OpenSource Ecommerce Solution
  http://www.AbanteCart.com

  Copyright © 2011-2017 Belavier Commerce LLC

  This source file is subject to Open Software License (OSL 3.0)
  License details is bundled with this package in the file LICENSE.txt.
  It is also available at this URL:
  <http://www.opensource.org/licenses/OSL-3.0>

 UPGRADE NOTE:
   Do not edit or add to this file if you wish to upgrade AbanteCart to newer
   versions in the future. If you wish to customize AbanteCart for your
   needs please refer to http://www.AbanteCart.com for more information.
------------------------------------------------------------------------------*/
if (!defined('DIR_CORE') || !IS_ADMIN) {
	header('Location: static_pages/');
}

if (defined('IS_DEMO') && IS_DEMO) {
	header('Location: static_pages/demo_mode.php');
}

/**
 * Class ControllerResponsesSaleContact
 * @property ModelToolImportProcess $model_tool_import_process
 */
class ControllerResponsesToolImportProcess extends AController {
	public $data = array();
	public $errors = array();

    public function __construct($registry, $instance_id, $controller, $parent_controller = '') {
        parent::__construct($registry, $instance_id, $controller, $parent_controller);

        $this->loadLanguage('tool/import_export');
    }

    public function buildTask(){
		$this->data['output'] = array();
		//init controller data
		$this->extensions->hk_InitData($this,__FUNCTION__);

		if ($this->request->is_POST() && $this->_validate()) {
            $imp_data = array_merge($this->session->data['import_map'], $this->session->data['import']);

			$this->loadModel('tool/import_process');
			$task_details = $this->model_tool_import_process->createTask('import_wizard_'.date('Ymd-H:i:s'), $imp_data);
			$task_api_key = $this->config->get('task_api_key');

			if (!$task_details) {
				$this->errors = array_merge($this->errors,$this->model_tool_import_process->errors);
				$error = new AError("File Import Error: \n ".implode(' ', $this->errors));
				return $error->toJSONResponse('APP_ERROR_402',
										array( 'error_text' => implode(' ', $this->errors),
												'reset_value' => true
										));
			} elseif (!$task_api_key){
				$error = new AError('files import error');
				return $error->toJSONResponse('APP_ERROR_402',
										array( 'error_text' => 'Please set up Task API Key in the settings!',
											   'reset_value' => true
										));
			} else {
				$task_details['task_api_key'] = $task_api_key;
				$task_details['url'] = HTTPS_SERVER.'task.php';
				$this->data['output']['task_details'] = $task_details;
			}

		} else {
			$error = new AError(implode('<br>', $this->errors));
			return $error->toJSONResponse('VALIDATION_ERROR_406',
									array( 'error_text' => implode('<br>', $this->errors),
											'reset_value' => true
									));
		}

		//update controller data
    	$this->extensions->hk_UpdateData($this,__FUNCTION__);

		$this->load->library('json');
		$this->response->addJSONHeader();
		$this->response->setOutput( AJson::encode($this->data['output']) );

	}

	public function complete(){
		//init controller data
		$this->extensions->hk_InitData($this,__FUNCTION__);

		$task_id = (int)$this->request->post['task_id'];
		if (!$task_id) {
			return null;
		}

		//check task result
		$tm = new ATaskManager();
		$task_info = $tm->getTaskById($task_id);
		$task_result = $task_info['last_result'];
		if ($task_result) {
			$tm->deleteTask($task_id);
			$result_text = sprintf($this->language->get('text_success_import'),$task_info['settings']['processed']);
		} else {
			$result_text = $this->language->get('text_task_failed');
		}

		//update controller data
        $this->extensions->hk_UpdateData($this,__FUNCTION__);

		$this->load->library('json');
		$this->response->addJSONHeader();
		$this->response->setOutput( AJson::encode(array(
													'result' => $task_result,
													'result_text' => $result_text ))
		);
	}

	public function restartTask(){
		$this->data['output'] = array();
		//init controller data
		$this->extensions->hk_InitData($this,__FUNCTION__);

		$task_id = (int)$this->request->get_or_post('task_id');
		$task_api_key = $this->config->get('task_api_key');
		$etas = array();
		if ($task_id) {
			$tm= new ATaskManager();

			$steps = $tm->getTaskSteps($task_id);
			foreach($steps as $step){
				if(!$step['settings']['to']){
					$tm->deleteStep($step['step_id']);
				}else{
					$tm->updateStep($step['step_id'], array ('status' => 1));
					$etas[$step['step_id']] = $step['max_execution_time'];
				}
			}

			$task_details = $tm->getTaskById($task_id);
			if (!$task_details || !$task_details['steps']) {
				//remove task when it does not contain steps
				if(!$task_details['steps']){
					$tm->deleteTask($task_id);
				}
				$error_text = "Import of File Error: Cannot to restart task #".$task_id.'. Task removed.';
				$error = new AError( $error_text );
				return $error->toJSONResponse('APP_ERROR_402',
										array( 'error_text' => $error_text,
												'reset_value' => true
										));
			} elseif (!$task_api_key) {
				$error = new AError('files backup error');
				return $error->toJSONResponse('APP_ERROR_402',
										array( 'error_text' => 'Please set up Task API Key in the settings!',
											   'reset_value' => true
										));
			} else {
				$task_details['task_api_key'] = $task_api_key;
				$task_details['url'] = HTTPS_SERVER . 'task.php';
				//change task status
				$task_details['status'] = $tm::STATUS_READY;
				$tm->updateTask($task_id, array('status' => $tm::STATUS_READY));
			}

			foreach ($etas as $step_id => $eta) {
				$task_details['steps'][$step_id]['eta'] = $eta;
			}

			$this->data['output']['task_details'] = $task_details;

		} else {
			$error = new AError(implode('<br>', $this->errors));
			return $error->toJSONResponse('VALIDATION_ERROR_406',
									array( 'error_text' => 'Unknown task ID.',
											'reset_value' => true
									));
		}

		//update controller data
    	$this->extensions->hk_UpdateData($this,__FUNCTION__);

		$this->load->library('json');
		$this->response->addJSONHeader();
		$this->response->setOutput( AJson::encode($this->data['output']) );

	}


	public function incomplete(){
		//init controller data
		$this->extensions->hk_InitData($this,__FUNCTION__);
		$this->loadModel('user/user');
		$this->data = $this->language->getASet('sale/contact');

		$tm = new ATaskManager();
		$incomplete = $tm->getTasks(array(
				'filter' => array(
						'name' => 'send_now'
				)
		));

		$k = 0;
		foreach($incomplete as $incm_task){
			//show all incomplete tasks for Top Administrator user group
			if($this->user->getUserGroupId() != 1){
				if ($incm_task['starter'] != $this->user->getId()){
					continue;
				}
			}
			//define incomplete tasks by last time run
			$max_exec_time = (int)$incm_task['max_execution_time'];
			if (!$max_exec_time) {
				//if no limitations for execution time for task - think it's 2 hours
				//$max_exec_time = 7200;
				$max_exec_time = 7200;
			}
			if (time() - dateISO2Int($incm_task['last_time_run']) > $max_exec_time ) {

				//get some info about task, for ex message-text and subject
				$steps = $tm->getTaskSteps($incm_task['task_id']);
				if(!$steps){
					$tm->deleteTask($incm_task['task_id']);
				}
				$user_info = $this->model_user_user->getUser($incm_task['starter']);
				$incm_task['starter_name'] = $user_info['username']. ' '.$user_info['firstname']. ' '.$user_info['lastname'];
				$step = current($steps);
				$step_settings = $step['settings'];
				if($step_settings['subject']){
					$incm_task['subject'] = $step_settings['subject'];
				}
				$incm_task['message'] = mb_substr($step_settings['message'],0, 300);
				$incm_task['date_added'] = dateISO2Display($incm_task['date_added'], $this->language->get('date_format_short').' '.$this->language->get('time_format'));
				$incm_task['last_time_run'] = dateISO2Display($incm_task['last_time_run'], $this->language->get('date_format_short').' '.$this->language->get('time_format'));
				$incm_task['sent'] = sprintf($this->language->get('text_sent'),$incm_task['settings']['sent'], $incm_task['settings']['recipients_count']);

				$this->data['tasks'][$k] = $incm_task;
			}

			$k++;
		}

		$this->data['restart_task_url'] = $this->html->getSecureURL('r/sale/contact/restartTask');
		$this->data['complete_task_url'] = $this->html->getSecureURL('r/sale/contact/complete');
		$this->data['abort_task_url'] = $this->html->getSecureURL('r/sale/contact/abort');

		$this->view->batchAssign($this->data);
		$this->processTemplate('responses/sale/contact_incomplete.tpl');
		//update controller data
		$this->extensions->hk_UpdateData($this,__FUNCTION__);


	}


	private function _validate() {
		if (!$this->user->canModify('sale/contact')) {
			$this->errors['warning'] = $this->language->get('error_permission');
            return false;
		}

        if (!$this->session->data['import_map'] || !$this->session->data['import']) {
            $this->errors['warning'] = $this->language->get('error_data_corrupted');
            return false;
        }

		return true;
	}

}
