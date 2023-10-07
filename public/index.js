// This is just scratchpad

const myRequest = new Request("index.php");

fetch(myRequest)
    .then((response) => {
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }

        return response.json();
    })
    .then((response) => {
        elm = document.querySelector("#out")

        html = "";

        for (const [network, leases] of Object.entries(response)) {
			if ( typeof leases === "object" && leases !== null ) {
                html += "<table><caption>" + network + "</caption>";
                html += "<thead><tr>";
                html += "<th>Hostname</th>";
                html += "<th>MAC Address</th>";
                html += "<th>IP Address</th>";
                html += "<th>Wifi?</th>";
                html += "</tr></thead>";
                html += "<tbody>";

                for (const [ipAddress, lease] of Object.entries(leases)) {
                    macAddress = lease.mac;
                    hostname = lease["client-hostname"]
                    wifi = "N";

                    // TODO: qutebrowser doesn't support Object.hasOwn
                    //       check for a polyfill and use that
                    isWifi = lease.hasOwnProperty("wifi");

                    if ( isWifi === true ) {
                        wifi = "Y";
                    }

                    html += "<tr><td>" + hostname + "</td>";
                    html += "<td>" + macAddress + "</td>";
                    html += "<td>" + ipAddress + "</td>";
                    html += "<td>" + wifi + "</td>";
                    html += "</tr>";
                }

                html += "</tbody></table>";
			} else {
				html += "<div>" + network + " => none</div>";
			}

        }

		elm.innerHTML = html;
    });

