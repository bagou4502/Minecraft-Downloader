#!/bin/bash

# Define Java version URLs
JAVA_8_URL="https://builds.openlogic.com/downloadJDK/openlogic-openjdk-jre/8u392-b08/openlogic-openjdk-jre-8u392-b08-linux-x64.tar.gz"
JAVA_16_URL="https://github.com/adoptium/temurin16-binaries/releases/download/jdk-16.0.2%2B7/OpenJDK16U-jdk_x64_linux_hotspot_16.0.2_7.tar.gz"
JAVA_17_URL="https://builds.openlogic.com/downloadJDK/openlogic-openjdk-jre/17.0.9+9/openlogic-openjdk-jre-17.0.9+9-linux-x64.tar.gz"

# Create directories
mkdir -p java/8 java/16 java/17

# Download and extract Java versions
echo "Downloading and extracting Java 8..."
curl -L $JAVA_8_URL | tar -xz --strip-components=1 -C java/8

echo "Downloading and extracting Java 16..."
curl -L $JAVA_16_URL | tar -xz --strip-components=1 -C java/16

echo "Downloading and extracting Java 17..."
curl -L $JAVA_17_URL | tar -xz --strip-components=1 -C java/17

echo "Java versions downloaded and extracted successfully."
