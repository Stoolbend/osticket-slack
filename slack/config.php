<?php

require_once INCLUDE_DIR . 'class.plugin.php';

class SlackPluginConfig extends PluginConfig {

    function getDepartmentsList() {
        $category = array();
        $sql = "SELECT `dept_id` AS `id`, `dept_name` AS `department` FROM ".TABLE_PREFIX."department";
        $result = db_query($sql);
        while($row = db_fetch_array($result)) {
            $category[$row['id']] = $row['department'];
        }
        return $category;
    }

    function getOptions() {
        $options = array(
            'slack' => new SectionBreakField(array(
                'label' => 'Slack notifier',
            )),
            'slack-webhook-url' => new TextboxField(array(
                'label' => 'Webhook URL',
                'configuration' => array('size'=>100, 'length'=>200),
            )),
            'notify-assigned-user-dm' => new BooleanField(array(
              'label' => 'DM Assigned User',
                'configuration' => array('size'=>100, 'length'=>200),
            )),
        );

        $options['department_title'] = new SectionBreakField(
            array(
                'label' => 'Departments',
            )
        );

        foreach ($this->getDepartmentsList() as $id => $department) {
            $options['slack_department_id_'.$id] = new TextboxField(
                array(
                    'id' => "slack_department_id_".$id,
                    'label' => $department,
                    'hint' => "#slack-channel",
                    'configuration' => array('size'=>40, 'length'=>60),
                    )
                );
        }
        return $options;
    }
}

?>
