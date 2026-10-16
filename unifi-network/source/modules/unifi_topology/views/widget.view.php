<?php
(new CWidgetView($data))
    ->setVar('topology', $data['topology'])
    ->setVar('topology_error', $data['topology_error'])
    ->setVar('traffic_graph', $data['traffic_graph'])
    ->setVar('locale', $data['locale'])
    ->show();
