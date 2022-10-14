# Configuring VSCode

## Using VSCode thorugh WSL

VSCode requires `wget`, you may install it like so:

```bash
sudo apt install wget
```

## Launch VSCode

Set your active directory to the `peerassess` folder and run the `code` command to launch VSCode in that folder:

```bash
cd <path/to/peerassess>
code .
```

## VSCode Extensions

To help with development, here are several recommended plugins:

- PHP Debug
- PHP Inteliphense
- PHP Sniffer
- MySQL

### MySQL Extension Usage

This extension allows you to view your database live. You may also use PhpMyAdmin as a web-hosted alternative.

You may add a new connection with the following (default) settings:

```
Host:     localhost
User:     moodle
Password: moodle
Port:     3306 (default)
```

You may then access the list of tables and `Select Top 1000` to list database records in the VSCode window. 

---

## The End

Give yourself a pat on the back for making it this far and in one piece. Good luck and happy coding!
