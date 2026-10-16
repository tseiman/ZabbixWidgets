<?php
$form = new CWidgetFormView($data);
$form->addField(new CWidgetFieldMultiSelectHostView($data['fields']['hostid']));
$form->addField(new CWidgetFieldTimePeriodView($data['fields']['time_period']));
$form->addField((new CWidgetFieldTextBoxView($data['fields']['exclude_expression']))->setFieldHint(makeHelpIcon(\Modules\PbsSummary\Includes\I18n::t('Matches are excluded from counts and details. Empty = no filter. Fields: worker_type, status, worker_id, user, node. Operators: ===, !==, &&, ||, !, and parentheses. Quoted strings are case-sensitive. Example: ').\Modules\PbsSummary\Includes\TaskFilter::DEFAULT_EXPRESSION)));
$form->show();
