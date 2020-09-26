<?php

class DomainHealthCheckPlugin extends Plugin
{
    private $supported_modules = ['cpanel', 'direct_admin'];

    public function __construct()
    {
        $this->loadConfig(dirname(__FILE__) . DS . 'config.json');

        Language::loadLang('domain_health_check_plugin', null, dirname(__FILE__) . DS . 'language' . DS);

        Loader::loadComponents($this, ['Input', 'Record']);
    }

    public function install($plugin_id)
    {
        // check for PHP version
        if(version_compare(PHP_VERSION, '7.2.0', '<='))
        {
            $this->Input->setErrors(['php'=> ['version'=> sprintf(Language::_('dhc.php.version', true), PHP_VERSION)]]);
            return;
        }

        $this->installCron('domain_health_check_tlds_file');

        // Force cron after install
        $this->cron('domain_health_check_tlds_file');
    }

    public function uninstall($plugin_id, $last_instance)
    {
        $this->deleteCronTask('domain_health_check_tlds_file', $last_instance);

        $this->deleteDirectory($this->getUploadDirectory());
    }

    private function deleteCronTask($task, $last_instance = false)
    {
        Loader::loadModels($this, ['CronTasks']);

        // Delete the cron task run
        if (($task_run = $this->CronTasks->getTaskRunByKey($task, 'domain_health_check'))) {
            $this->CronTasks->deleteTaskRun($task_run->task_run_id);
        }

        // Delete the cron task only if this is the last instance
        if ($last_instance &&
            ($cron_task = $this->CronTasks->getByKey($task, 'domain_health_check'))
        ) {
            $this->CronTasks->delete($cron_task->id, 'domain_health_check');
        }
    }

    public function installCron($task)
    {
        Loader::loadModels($this, ['CronTasks']);

        $cronTask = [
            'key' => $task,
            'task_type' => 'plugin',
            'dir' => 'domain_health_check',
            'name' => Language::_('dhc.cron.name', true),
            'description' => Language::_('dhc.cron.desc', true),
            'type' => 'interval',
            'type_value' => 10080, // once a week
            'enabled' => 1
        ];

        // Delete any current cron tasks
        if (($cron = $this->CronTasks->getByKey($cronTask['key'], $cronTask['dir'], $cronTask['task_type'])))
            $this->CronTasks->deleteTask($cron->id, $cronTask['task_type'], $cronTask['dir']);

        // Create the cron task
        $cron = $this->CronTasks->add($cronTask);

        if (($errors = $this->CronTasks->errors())) {
            $this->Input->setErrors($errors);
            return false;
        }

        // Create the cron task run
        $task_vars = [
            'enabled' => $cronTask['enabled'],
            $cronTask['type'] => $cronTask['type_value']
        ];

        $this->CronTasks->addTaskRun($cron, $task_vars);

        if (($errors = $this->CronTasks->errors())) {
            $this->Input->setErrors($errors);
            return false;
        }

        return true;
    }

    protected function getUploadDirectory()
    {
        Loader::loadComponents($this, ['SettingsCollection']);

        // Get the uploads directory
        $uploads_dir = $this->SettingsCollection->fetchSetting(
            null,
            Configure::get('Blesta.company_id'),
            'uploads_dir'
        );
        $uploads_dir = isset($uploads_dir['value']) ? $uploads_dir['value'] : PLUGINDIR . DS . 'domain_health_check' . DS . 'uploads' . DS;

        return $uploads_dir . Configure::get('Blesta.company_id') . DS . 'domain_health_check' . DS;
    }

    private function deleteDirectory($directory)
    {
        if(!is_dir($directory))
            return;

        $files = array_diff(scandir($directory), ['.','..']);

        foreach ($files as $file) {
            $f = $directory . DS . $file;

            if(is_dir($f))
                $this->deleteDirectory($f);
            else
                unlink($f);
        }

        return rmdir($directory);
    }

    public function cron($key)
    {
        if($key != 'domain_health_check_tlds_file')
            return;

        Loader::loadComponents($this, ['Upload']);

        $upload_dir = $this->getUploadDirectory();
        $file_dest = $upload_dir . 'tlds-alpha-by-domain.txt';

        $this->Upload->createUploadPath($upload_dir);

        $fd = fopen($file_dest, 'w');

        $options = [
            CURLOPT_FILE    => $fd,
            CURLOPT_TIMEOUT =>  15, // timeout after 15 seconds
            CURLOPT_URL     => 'https://data.iana.org/TLD/tlds-alpha-by-domain.txt'
        ];

        $curl = curl_init();
        curl_setopt_array($curl, $options);
        curl_exec($curl);
        curl_close($curl);
        fclose($fd);
    }

    /**
     * Returns whether this plugin provides support for setting admin or client service tabs
     * @see Plugin::getAdminServiceTabs
     * @see Plugin::getClientServiceTabs
     *
     * @return bool True if the plugin supports service tabs, or false otherwise
     */
    public function allowsServiceTabs()
    {
        return true;
    }

    /**
     * Returns all tabs to display to a client when managing a service
     *
     * @param stdClass $service A stdClass object representing the selected service
     * @return array An array of tabs in the format of method => array where array contains:
     *
     *  - name (required) The name of the link
     *  - icon (optional) use to display a custom icon
     *  - href (optional) use to link to a different URL
     *      Example:
     *      array('methodName' => "Title", 'methodName2' => "Title2")
     *      array('methodName' => array('name' => "Title", 'icon' => "icon"))
     */
    public function getClientServiceTabs(stdClass $service)
    {
        return $this->generateServiceTab($service, 'client');
    }

    /**
     * Returns all tabs to display to an admin when managing a service
     *
     * @param stdClass $service An stdClass object representing the selected service
     * @return array An array of tabs in the format of method => array where array contains:
     *
     *  - name (required) The name of the link
     *  - href (optional) use to link to a different URL
     *      Example:
     *      array('methodName' => "Title", 'methodName2' => "Title2")
     *      array('methodName' => array('name' => "Title", 'href' => "https://blesta.com"))
     */
    public function getAdminServiceTabs(stdClass $service)
    {
        return $this->generateServiceTab($service, 'admin');
    }

    private function generateServiceTab(stdClass $service, $view = 'client')
    {
        $service_tabs = [];
        $function_name = ($view === 'client') ? 'tabDomainHealthCheck' : 'tabAdminDomainHealthCheck';

        $module = $this->getModuleByService($service);
        if ($module && in_array($module->class, $this->supported_modules)) {
            $service_tabs = [
                $function_name => [
                    'name' => Language::_('dhc.name', true),
                    'icon' => 'fa fa-heartbeat' // should not effect admin tabs
                ]
            ];
        }

        return $service_tabs;
    }

    /**
     * Displays the custom tab defined for domain health check within the client area
     *
     * @param stdClass $service An stdClass object representing the service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The content of the tab
     */
    public function tabDomainHealthCheck(stdClass $service, array $get = null, array $post = null, array $files = null)
    {
        return $this->generateTab('client', $service, $get, $post, $files);
    }

    /**
     * Displays the custom tab defined for domain health check within the admin area
     *
     * @param stdClass $service An stdClass object representing the service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The content of the tab
     */
    public function tabAdminDomainHealthCheck(stdClass $service, array $get = null, array $post = null, array $files = null)
    {
        return $this->generateTab('admin', $service, $get, $post, $files);
    }

    protected function generateTab($view, stdClass $service, array $get = null, array $post = null, array $files = null)
    {
        $this->view = new View();
        Loader::loadHelpers($this, ['Html']);
        Loader::loadModels($this, [
            'ModuleManager',
            'DomainHealthCheck.DomainHealthCheckModel',
            'DomainHealthCheck.DomainHealthCheckPanels'
        ]);

        // Get module info
        // Be careful, for what ever reason getRow does not set boolean values
        // correctly but treats them as strings!
        $meta = $this->ModuleManager->getRow($service->module_row_id)->meta;
        $type = $service->package->meta->type;
        $module = $this->getModuleByService($service);

        $control_panel_info = $this->extract_domain_user($service, sprintf('%s_domain', $module->class), sprintf('%s_username', $module->class));

        // admin vs client area details
        $get_count = 4;
        $get_index = 3;
        if($view === 'admin')
        {
            $get_count = 5;
            $get_index = 4;
        }

        // List all domains then perform health check
        if($type === 'reseller')
        {
            // dynamically call the method based on the module type
            $domains = $this->DomainHealthCheckPanels->{sprintf('get_%s_accounts', $module->class)}($meta, $control_panel_info);

            // Perform health check
            if(count($get) === $get_count && array_key_exists($get[$get_index], $domains))
            {
                $domain = $get[$get_index];
                $this->view->setView(sprintf('tab_%s_domain_health_check_results', $view), 'DomainHealthCheck.default');
                $this->view->set('data', $this->DomainHealthCheckModel->performHealthCheck($domain));
            // Domain not found, show list of all domains owned by reseller
            } else {
                $this->view->setView(sprintf('tab_%s_domain_health_check_reseller', $view), 'DomainHealthCheck.default');
                $this->view->set('domains', $domains);
            }
        // Perform health check
        } else if($type === 'standard' /*cPanel*/ || $type === 'user' /*Direct Admin*/) {
            $this->view->setView(sprintf('tab_%s_domain_health_check_results', $view), 'DomainHealthCheck.default');
            $this->view->set('data', $this->DomainHealthCheckModel->performHealthCheck($control_panel_info['domain']));
        }

        return $this->view->fetch();
    }

    protected function extract_domain_user(stdClass $service, $domain, $user)
    {
        // Get Domain and user
        foreach($service->fields as $key => $value)
            if($value->key === $domain)
                $domain = $value->value;
            else if($value->key === $user)
                $user = $value->value;

        return [
            'domain'    => $domain,
            'user'      => $user
        ];
    }

    /**
     * Returns the module associated with a given service
     *
     * @param stdClass $service An stdClass object representing the selected service
     * @return mixed A stdClass object representing the module for the service
     */
    protected function getModuleByService(stdClass $service)
    {
        return $this->Record->select('modules.*')->
            from('module_rows')->
            innerJoin('modules', 'modules.id', '=', 'module_rows.module_id', false)->
            where('module_rows.id', '=', $service->module_row_id)->
            fetch();
    }

}
