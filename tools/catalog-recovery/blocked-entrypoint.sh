#!/bin/sh
# Deliberately unconditional. This package prepares evidence, not a running site.
printf '%s\n' 'BLOCKED: preparation only. Database sanitization and WordPress startup are not qualified.' >&2
exit 78
