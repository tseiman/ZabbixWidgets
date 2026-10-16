#!/usr/bin/env python3
import json
import re
import subprocess
import unittest
from pathlib import Path

import yaml

ROOT = Path(__file__).resolve().parents[2]
TEMPLATE = ROOT / "unifi-network/source/templates/unifi_network_topology_zabbix74.yaml"
MODULE = ROOT / "unifi-network/source/modules/unifi_topology"


def template():
    return yaml.safe_load(TEMPLATE.read_text())["zabbix_export"]["templates"][0]


def run_javascript(script, value, macro=None):
    if macro is not None:
        script = script.replace("{#UNIFI.MAC}", macro)
    harness = (
        "const value = " + json.dumps(json.dumps(value)) + ";\n"
        "const result = (() => {\n" + script + "\n})();\n"
        "process.stdout.write(JSON.stringify(result));\n"
    )
    completed = subprocess.run(
        ["node", "-e", harness], check=True, capture_output=True, text=True
    )
    return json.loads(completed.stdout)


def javascript_step(owner):
    for step in owner.get("preprocessing", []):
        if step["type"] == "JAVASCRIPT":
            return step["parameters"][0]
    raise AssertionError(f"No JavaScript preprocessing in {owner['key']}")


class TemplateTrafficTest(unittest.TestCase):
    def setUp(self):
        current = template()
        self.items = {item["key"]: item for item in current.get("items", [])}
        self.rules = {rule["key"]: rule for rule in current.get("discovery_rules", [])}

    def test_collects_device_statistics_and_discovers_switches_and_access_points(self):
        self.assertIn("unifi.devices.json", self.items)
        master = self.items["unifi.devices.json"]
        self.assertEqual(
            master["url"],
            "https://{$UNIFI_NETWORK_API_ADDRESS}/proxy/network/api/s/default/stat/device",
        )
        self.assertEqual(master["headers"][0]["value"], "{$UNIFI_NETWORK_API_KEY}")

        self.assertIn("unifi.device.discovery", self.rules)
        rule = self.rules["unifi.device.discovery"]
        discovered = json.loads(run_javascript(
            javascript_step(rule),
            {
                "data": [
                    {"type": "usw", "mac": "AA-BB-CC-DD-EE-01", "name": "Switch"},
                    {"type": "uap", "mac": "aa:bb:cc:dd:ee:02", "name": "AP"},
                    {"type": "ugw", "mac": "aa:bb:cc:dd:ee:03", "name": "Gateway"},
                    {"type": "usw", "mac": "invalid", "name": "Broken"},
                    {"type": "usw", "mac": "aa:bb:cc:dd:ee:01", "name": "Duplicate"},
                ]
            },
        ))
        self.assertEqual(
            discovered,
            [
                {"{#UNIFI.MAC}": "aa:bb:cc:dd:ee:01", "{#UNIFI.NAME}": "Switch", "{#UNIFI.TYPE}": "usw"},
                {"{#UNIFI.MAC}": "aa:bb:cc:dd:ee:02", "{#UNIFI.NAME}": "AP", "{#UNIFI.TYPE}": "uap"},
            ],
        )

    def test_rate_items_sum_unique_physical_ports_and_fall_back_to_ap_uplink(self):
        prototypes = {
            item["key"]: item
            for item in self.rules["unifi.device.discovery"]["item_prototypes"]
        }
        rx = prototypes["unifi.device.rx.rate.bps[{#UNIFI.MAC}]"]
        tx = prototypes["unifi.device.tx.rate.bps[{#UNIFI.MAC}]"]
        payload = {
            "data": [
                {
                    "type": "usw",
                    "mac": "aa:bb:cc:dd:ee:01",
                    "port_table": [
                        {"port_idx": 1, "rx_bytes-r": 10, "tx_bytes-r": 20},
                        {"port_idx": 2, "rx_bytes-r": "5", "tx_bytes-r": "7"},
                        {"port_idx": "02", "rx_bytes-r": 500, "tx_bytes-r": 500},
                        {"port_idx": 99, "type": "lag", "rx_bytes-r": 1000, "tx_bytes-r": 1000},
                        {"ifname": "bond0", "rx_bytes-r": 1000, "tx_bytes-r": 1000},
                    ],
                },
                {
                    "type": "uap",
                    "mac": "aa:bb:cc:dd:ee:02",
                    "port_table": [],
                    "uplink": {"rx_bytes-r": 3, "tx_bytes-r": 4},
                },
            ]
        }
        self.assertEqual(run_javascript(javascript_step(rx), payload, "aa:bb:cc:dd:ee:01"), 120)
        self.assertEqual(run_javascript(javascript_step(tx), payload, "aa:bb:cc:dd:ee:01"), 216)
        self.assertEqual(run_javascript(javascript_step(rx), payload, "aa:bb:cc:dd:ee:02"), 24)
        self.assertEqual(run_javascript(javascript_step(tx), payload, "aa:bb:cc:dd:ee:02"), 32)

    def test_counter_items_provide_change_per_second_fallback(self):
        prototypes = {
            item["key"]: item
            for item in self.rules["unifi.device.discovery"]["item_prototypes"]
        }
        payload = {
            "data": [
                {
                    "type": "usw",
                    "mac": "aa:bb:cc:dd:ee:01",
                    "port_table": [
                        {"port_idx": 1, "rx_bytes": 1000, "tx_bytes": 2000},
                        {"port_idx": 2, "rx_bytes": 3000, "tx_bytes": 4000},
                        {"port_idx": "02", "rx_bytes": 9000, "tx_bytes": 9000},
                        {"port_idx": 9, "type": "lag", "rx_bytes": 99999, "tx_bytes": 99999},
                    ],
                },
                {
                    "type": "uap",
                    "mac": "aa:bb:cc:dd:ee:02",
                    "port_table": [],
                    "uplink": {"rx_bytes": 7000, "tx_bytes": 8000},
                },
            ]
        }
        rx_counter = prototypes["unifi.device.rx.counter.bytes[{#UNIFI.MAC}]"]
        tx_counter = prototypes["unifi.device.tx.counter.bytes[{#UNIFI.MAC}]"]
        self.assertEqual(run_javascript(javascript_step(rx_counter), payload, "aa:bb:cc:dd:ee:01"), 4000)
        self.assertEqual(run_javascript(javascript_step(tx_counter), payload, "aa:bb:cc:dd:ee:01"), 6000)
        self.assertEqual(run_javascript(javascript_step(rx_counter), payload, "aa:bb:cc:dd:ee:02"), 7000)
        self.assertEqual(run_javascript(javascript_step(tx_counter), payload, "aa:bb:cc:dd:ee:02"), 8000)

        for direction in ("rx", "tx"):
            rate = prototypes[f"unifi.device.{direction}.counter.bps[{{#UNIFI.MAC}}]"]
            self.assertEqual(rate["master_item"]["key"], f"unifi.device.{direction}.counter.bytes[{{#UNIFI.MAC}}]")
            self.assertEqual(
                [step["type"] for step in rate["preprocessing"]],
                ["CHANGE_PER_SECOND", "MULTIPLIER"],
            )
            self.assertEqual(rate["preprocessing"][1]["parameters"], ["8"])
            self.assertEqual(rate["units"], "bps")
            self.assertNotEqual(rate.get("trends", "0"), "0")


class WidgetTrafficContractTest(unittest.TestCase):
    def test_widget_follows_dashboard_time_period(self):
        form = (MODULE / "includes/WidgetForm.php").read_text()
        view = (MODULE / "views/widget.edit.php").read_text()
        js = (MODULE / "assets/js/class.widget.js").read_text()
        self.assertIn("CWidgetFieldTimePeriod", form)
        self.assertIn("REFERENCE_DASHBOARD", form)
        self.assertIn("DATA_TYPE_TIME_PERIOD", form)
        self.assertIn("CWidgetFieldTimePeriodView", view)
        self.assertIn("onFeedback({type, value})", js)
        self.assertIn("CWidgetsData.DATA_TYPE_TIME_PERIOD", js)

    def test_backend_loads_bounded_traffic_history_for_topology_devices(self):
        topology = (MODULE / "includes/Topology.php").read_text()
        traffic = (MODULE / "includes/Traffic.php").read_text()
        self.assertIn("'hostid'", topology)
        self.assertIn("Traffic::load", topology)
        self.assertIn("getGraphAggregationByWidth", traffic)
        self.assertIn("unifi\\.device\\.(rx|tx)\\.(rate|counter)\\.bps", traffic)
        self.assertIn("MAX_POINTS", traffic)

    def test_frontend_binds_svg_mini_charts_to_non_manual_devices_only(self):
        js = (MODULE / "assets/js/class.widget.js").read_text()
        css = (MODULE / "assets/css/widget.css").read_text()
        self.assertIn("this.svg('g'", js)
        self.assertIn("'data-traffic-mac': node.mac", js)
        self.assertIn("this._openTraffic(node, mini)", js)
        self.assertIn("if (traffic) svg.append(traffic)", js)
        self.assertIn("if (!period || node.manual === true) return null", js)
        self.assertNotIn("this.svg('foreignObject'", js)
        self.assertNotIn("button.className = 'ut-traffic-mini'", js)
        self.assertIn("event.key === 'Enter' || event.key === ' '", js)
        self.assertIn("activator.focus()", js)
        self.assertIn(".ut-traffic-mini:focus-visible", css)

    def test_frontend_fills_charts_green_and_opens_pbs_style_overlay(self):
        js = (MODULE / "assets/js/class.widget.js").read_text()
        css = (MODULE / "assets/css/widget.css").read_text()
        manifest = json.loads((MODULE / "manifest.json").read_text())
        self.assertIn("_seriesAreaPaths", js)
        self.assertIn("ut-traffic-area", js)
        self.assertRegex(css, r"\.ut-traffic-area\{[^}]*fill:rgba\(44,153,117,")
        self.assertRegex(css, r"\.ut-traffic-chart\{[^}]*box-sizing:border-box")
        self.assertIn("document.body.append(dialog)", js)
        self.assertIn("dialog.showModal()", js)
        self.assertIn("dialog.ut-traffic-dialog", css)
        self.assertIn("::backdrop", css)
        self.assertEqual(manifest["version"], "0.3.3")

    def test_unknown_switch_warning_is_dismissible_only_for_widget_lifetime(self):
        js = (MODULE / "assets/js/class.widget.js").read_text()
        css = (MODULE / "assets/css/widget.css").read_text()
        self.assertIn("this.dismissedWarnings = new Set()", js)
        self.assertIn("this.dismissedWarnings.add(warning)", js)
        self.assertIn("Warnung schließen", js)
        self.assertIn("ut-notice-close", js)
        self.assertIn(".ut-notice-close", css)
        self.assertNotIn("localStorage", js)
        self.assertNotIn("sessionStorage", js)


if __name__ == "__main__":
    unittest.main()
