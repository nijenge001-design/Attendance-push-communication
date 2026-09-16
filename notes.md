Running Laravel Octane with FrankenPHP is a great performance choice, but it's important to manage it as a long-running process like your queue workers. While you *could* use Supervisor, the recommended approach for the HTTP server is a **systemd service**, as it's designed for managing daemons and offers better logging and lifecycle management.

### 🚀 Running Octane with FrankenPHP

Before setting up the service, ensure Octane and FrankenPHP are installed in your project:

```bash
composer require laravel/octane
php artisan octane:install --server=frankenphp
```

The command you have is correct for starting the server:

```bash
php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=6060
```

Binding to `0.0.0.0` makes it accessible on all network interfaces, which is necessary if you're not using a local reverse proxy.

### ⚙️ Configuring a systemd Service (Recommended)

Using systemd is the standard way to run Octane in production. It will automatically restart the service on failure and on server reboot.

Create a service file at `/etc/systemd/system/octane.service`:

```ini
[Unit]
Description=Laravel Octane (FrankenPHP) - AttendancePUSHCommunication
After=network.target mysql.service redis-server.service

[Service]
Type=simple
User=sga
Group=sga
WorkingDirectory=/home/sga/sga_project/AttendancePUSHCommunication
ExecStart=/usr/bin/php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=6060 --max-requests=500
Restart=always
RestartSec=5
Environment=APP_ENV=production

[Install]
WantedBy=multi-user.target
```

**Key points in this configuration:**

*   **`After=network.target`**: Ensures the service starts after the network is available.
*   **`User=sga` / `WorkingDirectory`**: Runs the process as your application user from the correct directory.
*   **`Restart=always` / `RestartSec=5`**: Restarts the service automatically if it crashes, with a 5-second delay.
*   **`--max-requests=500`**: This is a crucial production setting. It tells FrankenPHP to gracefully restart a worker after it has handled 500 requests, which helps mitigate the risk of memory leaks in long-running PHP processes.

### 🛠️ How to Manage the Service

Once the file is saved, use these commands to control Octane:

```bash
# Reload systemd to recognize the new service
sudo systemctl daemon-reload

# Enable the service to start on boot
sudo systemctl enable octane

# Start the service
sudo systemctl start octane

# Check its status
sudo systemctl status octane

# View live logs
sudo journalctl -u octane -f
```

### 💡 Important Considerations for Your Setup

1.  **Running Alongside Queue Workers**: Octane and your Supervisor-managed queue workers are separate, long-running processes. They will coexist fine on the same server. Just be mindful of your total resource usage. With 45 GB of RAM, you have plenty of headroom, but keep an eye on the memory usage of all processes.

2.  **Reverse Proxy**: While binding to `0.0.0.0` works, the recommended production setup is to bind Octane to `127.0.0.1` and place a reverse proxy (like Nginx or Caddy) in front of it to handle TLS/HTTPS, static files, and other web server tasks. This is a more secure and flexible architecture.

3.  **Deployment**: When you deploy new code, you must restart the Octane service to load the changes, just as you do with your queue workers. This is typically done in a deploy script with `sudo systemctl restart octane`. Octane also has a built-in `php artisan octane:reload` command for graceful worker reloads, but a full service restart is simpler for most deployments.

In short, your `octane:start` command is correct. The next step is to wrap it in a systemd service (rather than Supervisor) to ensure it runs reliably in the background. This setup will give you a high-performance application server that starts on boot, restarts on failure, and runs alongside your queue processing system.
