# Development Environment

Now, you will need to install `git` and `xampp`.

---

<br>

## Git

Run in WSL to install `git`:

```bash
sudo apt update
sudo apt install git
```

Verify your installation with

```bash
git --version
```

Configure your `git` user settings with:

```bash
sudo git config --global user.name "FIRST LAST"
sudo git config --global user.email "username@domain.com"
```

---

<br>

## XAMPP

<br>

### Download

Download the PHP 7.4 version of XAMPP for Linux [here](https://www.apachefriends.org/download.html).

You should have downloaded a file like `xampp-linux-x64-7.4.30-1-installer.run`.

<br>

### Execute Installer

Set executable permission on the `.run` file:

```bash
sudo chmod +x <path/to/file>
```

Running it will launch a GUI installer, followed by the XAMPP Control Panel once completed:

```
sudo ./<path/to/file>
```

<br>

### Set Up Database User

Start all the servers then go to `http://localhost` in your Windows browser to load the XAMPP welcome page.

Access the phpMyAdmin dashboard and create a new database user with details:

| Field    | Data      |
| -------- | --------- |
| Host     | localhost |
| Username | moodle    |
| Password | moodle    |

Ensure you check these boxes:

- Create database with same name and grant all privileges
- Grant all privileges on wildcard name (username\\_%)
- Global privileges: check all (for simplicity of development environment)

---

<br>

## Next: Install Moodle

Click [here](/docs/moodle.md).
