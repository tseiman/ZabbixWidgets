<?php
$form = new CWidgetFormView($data);
$form->addField(new CWidgetFieldMultiSelectHostView($data['fields']['hostid']));
$form->addField(new CWidgetFieldTimePeriodView($data['fields']['time_period']));
$form->addField(new CWidgetFieldIntegerBoxView($data['fields']['limit']));
$form->show();
