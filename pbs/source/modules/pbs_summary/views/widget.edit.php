<?php
$form = new CWidgetFormView($data);
$form->addField(new CWidgetFieldMultiSelectHostView($data['fields']['hostid']));
$form->addField(new CWidgetFieldTimePeriodView($data['fields']['time_period']));
$form->addField((new CWidgetFieldTextBoxView($data['fields']['exclude_expression']))->setFieldHint(makeHelpIcon('Treffer werden aus Zählung und Details ausgeschlossen. Leer = kein Filter. Felder: worker_type, status, worker_id, user, node. Operatoren: ===, !==, &&, ||, ! und Klammern. Texte in Anführungszeichen; Groß-/Kleinschreibung wird beachtet. Beispiel: '.\Modules\PbsSummary\Includes\TaskFilter::DEFAULT_EXPRESSION)));
$form->show();
