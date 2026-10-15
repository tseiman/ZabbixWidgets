<?php
$form = new CWidgetFormView($data);
$form->addField(new CWidgetFieldMultiSelectHostView($data['fields']['hostid']));
$form->addField(new CWidgetFieldTimePeriodView($data['fields']['time_period']));
$form->show();
