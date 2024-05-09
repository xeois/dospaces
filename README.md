# DigitalOcean Spaces Storage Plugin for osTicket

This plugin allows osTicket to store files and attachments in DigitalOcean Spaces, providing a scalable and secure storage solution.

## Prerequisites
Before you install this plugin, make sure you have:
- An osTicket installation
- A DigitalOcean Spaces account and API credentials

## Installation
## 1. Clone the Plugin:
```bash
git clone https://github.com/xeois/dospaces.git
```

## 2. Setup the Plugin Directory:
- Navigate to your osTicket installation directory.
- Place the dospaces folder inside include/plugins.

## 3. Configure the Plugin:
Move the make.php file from the dospaces folder to the plugins folder:
```bash
mv include/plugins/dospaces/make.php include/plugins/make.php
```

## 4. Hydrate Plugin:
In the terminal, navigate to the plugins folder:

```bash
cd include/plugins
```

```bash
php make.php hydrate
```

## 5. Install the Plugin via osTicket Admin Panel:
- Log in to your osTicket Admin panel.
- Navigate to **Admin Panel > Manage > Plugins**.
- Click on "Add New Plugin" and select the DigitalOcean Spaces Storage Plugin to install it.


### Configuration
After installing the plugin, you need to configure it with your DigitalOcean Spaces API credentials. This can be done through the plugin settings in the osTicket Admin panel.
