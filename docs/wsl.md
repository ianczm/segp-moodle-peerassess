# Windows Subsystem for Linux

This guide will exclusively use Windows PowerShell and assume you are always the `root` user. Edit permissions accordingly to follow along with a non-root user.

---

<br>

## Install WSL2

Since this step depends on your version of windows, please refer to these links instead.

- [Microsoft Guide: Install WSL (newer Windows)](https://learn.microsoft.com/en-us/windows/wsl/install)
- [Microsoft Guide: Install WSL (older Windows)](https://learn.microsoft.com/en-us/windows/wsl/install-manual)

WSL is installed correctly when you can run the following command and verify that WSL is running on version 2.

```powershell
wsl --status
```

---

<br>

## Install Linux

This guide is for manually setting up a completely fresh **Ubuntu 22.04** installation on your WSL.

If you have already installed Ubuntu from the Microsoft Store or elsewhere, you can

- Still follow these steps to set up a separate version of Ubuntu from the one you installed (recommended)
- Verify that your own version of Ubuntu is set up correctly

<br>

### Identify Architecture

Figure out your `processor_architecture` by typing the following command into PowerShell:

```powershell
echo $env:PROCESSOR_ARCHITECTURE
```

The output can be `AMD64` or `ARM64`, among others.

<br>

### Download Distribution

Download the base distribution `.tar.gz` for your architecture [here](http://cdimage.ubuntu.com/ubuntu-base/releases/22.04/release/).

<br>

### Install Distribution

Once finished downloading, run the following command to install the distribution as an instance:

```powershell
wsl --import <instanceName> <installation/directory> <path/to/downloaded/tar>
```

An example is like this:

```
wsl --import segp .\segp .\ubuntu-base-22.04-base-amd64.tar.gz
```

---

<br>

## Set Up Ubuntu

Run the following command to boot into the WSL terminal for your Ubuntu instance:

```powershell
wsl -d <instanceName>
```

You have booted WSL correctly when you see either $ or # following your Linux username.

If you are the root user, you may set up your password by running the command below:

```bash
passwd
```

<br>

### Install Base Packages

Update your repositories and install `sudo` for admin permissions and `nano` as a lightweight text editor:

```bash
apt update
apt install sudo
apt install nano
```

---

<br>

## WSL Filesystem

While logged into WSL, you may choose to view your Linux filesystem in Windows with:

```bash
explorer.exe .
```

---

<br>

## Next: Set Up Development Environment

Click [here](/docs/dev.md).
