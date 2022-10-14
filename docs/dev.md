# Development Environment

## Install Git

Run in WSL to install `git`:

```bash
sudo apt update
sudo apt install git
```

Verify your installation with

```bash
git --version
```

---

## Install XAMPP

Download the PHP 7.4 version of XAMPP for Linux [here](https://www.apachefriends.org/download.html).

You should have downloaded a file like `xampp-linux-x64-7.4.30-1-installer.run`.

Set active terminal directory to where you downloaded the file:

```bash
cd <folder/containing/file>
```

Set executable permission on this file so you can run it:

```bash
sudo chmod +x <path/to/file>
```

Then actually run it:

```
sudo ./<path/to/file>
```

This should run a GUI installer and then launch XAMPP Control Panel once completed.

Start all the servers then go to `http://localhost` to load the XAMPP welcome page.

Access the phpMyAdmin dashboard and create a new database user with details:

```
Host:       localhost
Username:   moodle
Password:   moodle
```

Check the boxes:

- Create database with same name and grant all privileges
- Grant all privileges on wildcard name (username\\_%)
- Global privileges: check all (for simplicity of development environment)

---

## Next: Install Moodle

Click [here](/docs/moodle.md).
