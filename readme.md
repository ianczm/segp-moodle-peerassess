# G52GRP Source Code

### COMP 2019 Software Engineering Group Project
#### Moodle Plugin for Peer Assessment

| Item | Detail |
| - | - |
| Date | 28-04-2022 |
| Prepared by | Group 10A |
| Supervisor | Mr Radu Muschevici |

---

### Group 10A Members
| Member | Student ID | Email |
| --- | --- | --- |
| Ian Chong Zhen Ming | 20313229 | hcyic1@nottingham.edu.my |
| Tan Lik Wei | 20208762 | hfylt4@nottingham.edu.my |
| Abdul Mateen Bin Abdul Saheed | 20194744 | hfyaa8@nottingam.edu.my |
| Gan Chi Hung | 20203996 | hfycg1@nottingham.edu.my |
| Chow Wen Jun | 20204046 | hfywc8@nottingham.edu.my |
| Tan Jun Yi | 20203636 | hfyjt4@nottingham.edu.my |

---

### Background

Peer Assessment enables students to evaluate their group members’ contribution to the overall group effort by using the members’ individual peer assessment scores as weightage to distribute the collective group mark to each individual according to merit as perceived by the peers. 

### Goal

Develop a Moodle plugin to automatically record PAs, calculate scores and students’ final grades, analyse the data, and create a downloadable report.

---

### Prerequisites

In order to get the Moodle plugin to work, a Moodle instance running on a LAMP environment must first be set up.

Please ensure the following steps have been completed before proceeding.

1.  Install Linux, Apache, MySQL and PHP
2.  Install Moodle and its dependencies

---

### Plugin Installation

#### Method 1 – Moodle Plugin Installer (Easiest)

Log in to Moodle as an admin, go to `Administration > Site administration > Plugins > Install plugins`, and upload the zip file in the Moodle Plugin Installer. If the plugin is not automatically detected, we will be prompted to add extra details in the ‘Show more’ section. Also, if the target directory is not writeable, a warning message will appear.

#### Method 2 – Manual Deployment

The plugin folder should be copied or cloned to the server activity modules folder at `/path/to/moodle/mod/` **and with the folder renamed to `peerassess`**. Then, head to `Site administration > Notifications` on Moodle to check for an installation message.

#### Confirmation of Installation

Please note that if you have installed the plugin via `Method 2`, the plugin will **not** work without the proper name.

At the end of installation, regardless of the method used, the result should be a newly created directory named `peerassess`, which is the root plugin directory.

```
/path/to/moodle/mod/peerassess
```

Upon starting Moodle and entering the `Site Administration` page, expect Moodle to perform dependency checks and automatically prompt you to `Upgrade Database` if it has not already done so during installation.

### How to Run and Use it

As administrators, click on `Turn editing on` then `Add an activity or resource` on the module page they want to use it on. Upon selecting `peerassess`, Moodle will redirect the user to the plugin setup interface.

---

### Future Development

* Allow administrators to manually override peer and final grades. 
* The ability to search for specific student records. 
* More helpful administrative statistics like average scores on each question, overall ratings, average ratings, etc. 
* Allow students to see their comments received and overall peer ratings for each question.
* Add other peer factors and grades calculation methods.
* Add mobile support to our plugin.

---

### Developer Notes

- [Setting up WSL](/docs/wsl.md)
- [Setting up Development Environment](/docs/dev.md)
