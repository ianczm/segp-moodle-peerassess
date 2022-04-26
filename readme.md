# SEGP Moodle Plugin for Peer Assessment

### Group: 
10a
### Background:
Peer Assessment enables students to evaluate their group members’ contribution to the overall group effort by using the members’ individual peer assessment scores as weightage to distribute the collective group mark to each individual according to merit as perceived by the peers. 
### Goal: 
Develop a Moodle plugin to automatically record PAs, calculate scores and students’ final grades, analyse the data, and create a downloadable report.
### How to Install this Moodle Plugin:
Method 1) 
<br/>
Log in to Moodle as an admin, go to Administration > Site administration > Plugins > Install plugins, and upload the zip file in the Moodle Plugin Installer. If the plugin is not automatically detected, we will be prompted to add extra details in the ‘Show more’ section. Also, if the target directory is not writeable, a warning message will appear. 
<br/><br/>
Method 2) 
<br/>
Deploy the plugin manually at the server. The plugin folder should be copied to the server file system location where the activity modules and resources are, which is /path/to/moodle/mod/, and then head to Site administration > Notifications on Moodle to check for an installation message. 
<br/>
### How to Run and Use it:
As administrators, click on "Turn editing on" then "Add an activity or resource" on the module page they want to use it on. Moodle will then redirect the user to the peerassess plugin setup interface.
### Future Development:
* Allow administrators to manually override peer and final grades. 
* The ability to search for specific student records. 
* More helpful administrative statistics like average scores on each question, overall ratings, average ratings, etc. 
* Allow students to see their comments received and overall peer ratings for each question.
* Add other peer factors and grades calculation methods.
* Add mobile support to our plugin.

