# Minecraft Downloader

## Overview
System is a specialized project developed for Bagou450, designed to streamline the downloading of various Minecraft server versions. It's an integral part of Bagou450's products, "Minecraft Versions Changer" and "Minecraft Modpacks Installer".

## Project Structure
The project categorizes numerous Minecraft server types, as follows:
- banner
- bungeecord
- catservers
- fabric
- folia
- forge
- magma
- mohist
- neoforge
- paper
- purpur
- snapshot
- spigot
- spongeforge
- spongevanilla
- travertine
- vanilla
- velocity
- waterfall

## Recommended Development Environment
- **Recommended IDE:** PhpStorm
- **PHP:** Minimum version 8.3
- **Composer:** Dependency manager

## Dependencies
List of Composer dependencies required for the project:
- guzzlehttp/guzzle: ^7.8
- vlucas/phpdotenv: ^5.6
- ext-json: *
- nesbot/carbon: ^2.72
- ivopetkov/html5-dom-document-php: 2.*
- ext-zip: *
- phpmailer/phpmailer: ^6.9
- symfony/filesystem: ^7.0
- symfony/finder: ^7.0

## Java Versions and Downloads
The project utilizes the following Java versions. Download the appropriate version for your operating system:

### For Windows
Download and place in corresponding directories:
- Java 8: [Download](https://builds.openlogic.com/downloadJDK/openlogic-openjdk-jre/8u392-b08/openlogic-openjdk-jre-8u392-b08-windows-x64.zip) (java/8-win)
- Java 16: [Download](https://github.com/adoptium/temurin16-binaries/releases/download/jdk-16.0.2%2B7/OpenJDK16U-jdk_x64_windows_hotspot_16.0.2_7.zip) (java/16-win)
- Java 17: [Download](https://builds.openlogic.com/downloadJDK/openlogic-openjdk-jre/17.0.9+9/openlogic-openjdk-jre-17.0.9+9-windows-x64.zip) (java/17-win)

### For Linux
Download and place in corresponding directories:
- Java 8: [Download](https://builds.openlogic.com/downloadJDK/openlogic-openjdk-jre/8u392-b08/openlogic-openjdk-jre-8u392-b08-linux-x64.tar.gz) (java/8)
- Java 16: [Download](https://github.com/adoptium/temurin16-binaries/releases/download/jdk-16.0.2%2B7/OpenJDK16U-jdk_x64_linux_hotspot_16.0.2_7.tar.gz) (java/16)
- Java 17: [Download](https://builds.openlogic.com/downloadJDK/openlogic-openjdk-jre/17.0.9+9/openlogic-openjdk-jre-17.0.9+9-linux-x64.tar.gz) (java/17)

On linux you can also run the "setup.bash" script to download and extract the Java versions automatically.

## License
This project is protected under the "Bagou450 Exclusive License" and cannot be used by others, even for personal use.

## Environment Configuration (.env)
Set up the environment variables in a `.env` file. Replace the placeholders with your actual configuration:
```dotenv
DEBUG="false"
SMTP_HOST="your_smtp_host"
SMTP_USER="your_smtp_user"
SMTP_PASS="your_smtp_password"
SMTP_PORT=your_smtp_port
SMTP_FROM_EMAIL="your_from_email"
SMTP_FROM_NAME="your_from_name"
SMTP_TO_EMAIL="your_to_email"
SMTP_TO_NAME="your_to_name"
```

## Error Handling
In case of an error, an email is sent to the address specified in the `SMTP_TO_EMAIL` variable in the `.env` file.

## Execution
- **Web:** Run the `index.php` file in a web environment.
- **CLI:** `index.php` can also be executed via the command line for CLI management.
