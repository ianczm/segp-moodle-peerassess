# Installing Peerassess Plugin

This is the plugin contained in this repository.

---

<br>

## The Mod Folder

This folder contains all [activity module](https://docs.moodle.org/dev/Activity_modules) plugins, and `peerassess` is one of them.

Install this plugin into `mod/peerassess`:

```bash
cd /opt/lampp/htdocs/mod
sudo git clone https://github.com/ianczm/segp-moodle-peerassess.git peerassess
```

---

<br>

## Install Plugin

Refresh the Moodle webpage. You should be prompted to install a new plugin. Follow the prompts and install any additional dependencies if required.

If the prompt does not appear, you may also try accessing the `Site Administration` and it should automatically trigger.

Once this is done, the plugin may be used normally by creating a Course and adding the Peerassess Plugin as an activity module.

---

<br>

## Next: Configuring VSCode

Click [here](/docs/vscode.md).
