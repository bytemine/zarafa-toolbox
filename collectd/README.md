# Collectd-Plugin for Zarafa

Basically just an exec-helper. 

Add this to your collectd-config on the Zarafa Host (assuming you placed the
script in /usr/local/bin). The user you run the plugin as, must be allowed to access the
zarafa system.

	LoadPlugin exec
	<Plugin exec>
		Exec "valid-user:valid-group" "/usr/local/bin/exec-zstats"
	</Plugin>

If the hostname reported by 'hostname' does not match the name configured in your collectd
collector, you can override that.
The interval is configured to 10 seconds. If your collectd instance runs with
a different interval, change that. 

	HOSTNAME="${COLLECTD_HOSTNAME:-$(hostname)}"
	INTERVAL="${COLLECTD_INTERVAL:-10}"


