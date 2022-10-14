# Installing Peerassess Plugin

## The Mod Folder

This folder contains all [activity module](https://docs.moodle.org/dev/Activity_modules) plugins, and `peerassess` is one of them.

To install `peerassess`, first set active directory to your `mod` folder.

```bash
cd </path/to/moodle>/mod
```

Example:

```bash
cd /opt/lampp/htdocs/mod
```

## Clone Repository

Clone the `peerassess` repository into a folder `mod/peerassess`.

```bash
sudo git clone https://github.com/ianczm/segp-moodle-peerassess.git peerassess
```

## Install Plugin

Refresh the Moodle webpage. You should be prompted to install a new plugin. Follow the prompts and install any additional dependencies if required.

If the prompt does not appear, you may also try accessing the `Site Administration` and it should automatically trigger.

Once this is done, the plugin may be used normally by creating a Course and adding the Peerassess Plugin as an activity module.

---

## Next: Configuring VSCode

Click [here](/docs/vscode.md).
