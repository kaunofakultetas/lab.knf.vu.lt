#!/bin/bash



# STEP 1: Create necessary files and directories
# ==============================================
# Data directories
mkdir -p ./_DATA/apache/{downloads,public}
mkdir -p ./_DATA/mysql

# Logs directories
mkdir -p ./_LOGS/apache/{logs,laravel_logs}

# SSO directories
mkdir -p ./_SSO/simplesaml/{config,metadata,cert}




# STEP 2: Run the stack
# =====================
sudo docker network create --subnet=172.18.0.0/24 external
sudo docker-compose down
sudo docker-compose up -d --build
