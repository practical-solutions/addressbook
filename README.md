﻿# Addressbook Plugin for DokuWiki

This is a first testing / working version!

Adds an addressbook functionality to DokuWiki. The search results are also displayed on the standards search page.

The contacts are stored in a sqlite3-database. The [sqlite-plugin](https://www.dokuwiki.org/plugin:sqlite) ist required.

## Usage

![](screenshots/search.png)

```
[ADDRESSBOOK:search]
```
Adds a search bar to perform a fulltext search


![](screenshots/list.png)

```
[ADDRESSBOOK:index]
```
Lists all contacts.


  * ``[ADRESSSBOOK:index?departments]`` - Separate List by departments

![](screenshots/addnew.png)

```
[ADDRESSBOOK:addcontact]
```
Provides a form with which contacts can be added


```
[ADDRESSBOOK:contact=<nr>]
```
Show all information about a contact.


```
[ADDRESSBOOK:print<?option1&option2>]
```

Creates a printable list

  * ``[ADDRESSBOOK:print?department]`` - Separate contacts by department
  * ``[ADDRESSBOOK:print?select=<name>]`` - Show only contacts from department ``<name>``



## Issues / Ideas

* Import and export CSV-Files
* Integration into DokuWikis search should be configurable
* Improve styling of the search box
* Add print styles for contact cards and the index list
* Improve index list showing specified amount of contacts with page flip


## Compatibility

Tested with
* PHP / **7.3**
* DokuWiki / **Hogfather**
* [sqlite-plugin](https://www.dokuwiki.org/plugin:sqlite) / **2020-11-18**


## Data storage

The data is stored ``data/meta/addressbook.sqlite3`` and can be backuped easily. An addressbook (sqlite3) with 1.000 contacts has a size of approximately 4.1 MB if every contact has a photo. The photo is scaled down and compressed, so it uses about 3-4kB. It is stored within the database as a blob (base64encoded).
