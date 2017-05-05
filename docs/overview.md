# What is Data Hub?
Data Hub is a general tool for importing and exporting user, course, and enrollment information on a schedule, via CSV files. This is a common method for integrating SIS/HR/ERP/MIS systems with an LMS.

## What Data Hub does not do
Data Hub does not include a set of SIS specific plug-ins; using DH requires that you can export CSV files from your SIS in a format that DH can process (see the sections on setting up user, course, and enrollment fields for specifics of the DH CSV format). The DH format is very similar to the format required for Moodle's standard flat file import.

## Data Hub Basic Overview
Moodle is a powerful system for delivering courses, however there are often times when users need to integrate with information from other systems such as SIS, EPR, HR, and Financial Record keeping systems. To facilitate this, Remote-Learner has built the Data Hub, a tool for 2 way communication of information between Moodle and other systems - users, courses, enrollments in and grades out. DH also provides a way to quickly set up Moodle courses by uploading a formatted CSV file and using template courses. Detailed logs are kept of import jobs, which can be viewed online and/or emailed to designated recipients.

## Update Frequency
### Inbound information (to Moodle)
Incoming information (users, user information, enrollments, courses, etc.) can be scheduled in minutes, hours, or days. You can run more than one schedule, for example to import users every 5 minutes and to import enrollments every hour. Import and export jobs can be scheduled in the Data Hub Plug-in Configuration as described on the Data Hub Block Configuration page of this manual.

### Outbound information (from Moodle)
Grade and course information is exported every 24 hours by default. This can be changed in the Data Hub Block settings.

## Data format(s)
The current version of Data Hub provides for import of CSV (comma separated values) files. CSV is a common format that can be easily created and/or edited in most data management tools (including Excel, OpenOffice, and Access).

## Data Categories

### User data
This is data about the user, that includes some or all of the information that goes in the user profiles in Moodle. See below for a detailed description of the user data handling in DH Basic.

### Course data
This is data about course properties that may be set by the data import. See below for a detailed description of these properties.

### Enrollment properties
This is data about the enrollment status of a user - which courses the user is enrolled in, what their status is, what their role in the class is, the completion status for their courses, etc. See below for a detailed description of these properties.

## Automating Data Import/Export

Data Hub can be scheduled to automatically import files placed in its import folder and load any new files that are placed there into Moodle. If your source data system (SIS/ERP/HRMS, etc.) can be set up to automatically export files, it can send them to the DH target folder in various ways – for instance via SCP, shell scripts, etc.

Since source systems are all different, we can't guarantee that a particular client system can be fully automated, but if it can be set to automatically export CSV files, then it is generally a simple matter for the system's administrator to set it to automatically export those files to the Data Hub Basic target folder. Once that is done, Data Hub will load the files as scheduled.
