<?php
(new CWidgetFormView($data))
    ->addField((new CWidgetFieldMultiSelectItemView($data['fields']['itemid']))->setPopupParameter('value_types', [ITEM_VALUE_TYPE_TEXT]))
    ->addField(new CWidgetFieldTextBoxView($data['fields']['host_pattern']))
    ->addField(new CWidgetFieldTextAreaView($data['fields']['bindings']))
    ->addField(new CWidgetFieldTextAreaView($data['fields']['manual_nodes']))
    ->addField(new CWidgetFieldTextAreaView($data['fields']['manual_links']))
    ->addField(new CWidgetFieldIntegerBoxView($data['fields']['max_age']))
    ->addField(new CWidgetFieldCheckBoxView($data['fields']['show_suppressed']))
    ->show();
