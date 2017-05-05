# Importing/Processing Files

Data Hub import files can be scheduled to process or can be manually processed. Import files should be scheduled to process in most instances. Manual processing should be used for testing small files only. Manual processing is currently limited to 28 seconds of processing time in our standard Data Hub installs. Scheduled processing will continue imports on subsequent cron runs when processing takes to long, manual processing does not do this.

## Scheduling Import Times

Scheduled imports process files uploaded to the the import files path, which is also referred to as the import file location in some areas of the documentation. The files can be uploaded to the import files path via SFTP. The import files path is listed in the Administration block > Site Administration > Plugins > Local plugins > Data Hub plugins > Version 1 import settings.

To access more documentation about imports, visit the Data Hub Import Documentation:

* [Importing/Processing Files](http://rlcommunity.remote-learner.net/mod/book/view.php?id=59&chapterid=760)
* [Accessing the Import, Export, and Log Folders Via SFTP](http://rlcommunity.remote-learner.net/mod/book/view.php?id=59&chapterid=523)
* [Setting Up User Import Files](http://rlcommunity.remote-learner.net/mod/book/view.php?id=59&chapterid=513)
* [Importing Course Information](http://rlcommunity.remote-learner.net/mod/book/view.php?id=59&chapterid=514)
* [Using Template Courses](http://rlcommunity.remote-learner.net/mod/book/view.php?id=59&chapterid=516)
* [Importing Enrollment Information](http://rlcommunity.remote-learner.net/mod/book/view.php?id=59&chapterid=515)
* [Importing Very Large User and Enrollment Files](http://rlcommunity.remote-learner.net/mod/book/view.php?id=59&chapterid=525)
