<?php
(new CWidgetView($data))
    ->setVar('topology', $data['topology'])
    ->setVar('topology_error', $data['topology_error'])
    ->show();
