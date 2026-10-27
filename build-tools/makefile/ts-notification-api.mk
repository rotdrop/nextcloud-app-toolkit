# Makefile fragment for generating a openapi typescript interfaces from the notifications app

include $(dir $(lastword $(MAKEFILE_LIST)))toolkit.mk

ifeq ($(TS_TYPES_DIR),)
TS_TYPES_DIR := $(BUILDDIR)/ts-types
endif

NOTIFICATION_API_IN = https://github.com/nextcloud/notifications/raw/refs/heads/stable$(CLOUD_MAX_VERSION)/openapi.json
TS_NOTIFICATION_API = $(TS_TYPES_DIR)/notification-api.d.ts

#@private
ts-notification-api: $(TS_NOTIFICATION_API)
.PHONY: ts-app-config

#@private
$(TS_NOTIFICATION_API): $(MAKEFILE_DEP) $(APP_TOOLKIT_BUILD_DIR)/makefile/ts-notification-api.mk
	mkdir -p $$(dirname $@)
	npx openapi-typescript $(NOTIFICATION_API_IN) -o $@
