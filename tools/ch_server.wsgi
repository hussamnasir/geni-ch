# ----------------------------------------------------------------------
# Copyright (c) 2016 Raytheon BBN Technologies
#
# Permission is hereby granted, free of charge, to any person obtaining
# a copy of this software and/or hardware specification (the "Work") to
# deal in the Work without restriction, including without limitation the
# rights to use, copy, modify, merge, publish, distribute, sublicense,
# and/or sell copies of the Work, and to permit persons to whom the Work
# is furnished to do so, subject to the following conditions:
#
# The above copyright notice and this permission notice shall be
# included in all copies or substantial portions of the Work.
#
# THE WORK IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
# OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
# MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
# NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
# HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
# WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
# OUT OF OR IN CONNECTION WITH THE WORK OR THE USE OR OTHER DEALINGS
# IN THE WORK.
# ----------------------------------------------------------------------

import sys
import os

# All of these sys.path.append calls can go alway
# when the WSGI configuration adds 'python-path' to
# the WSGIDaemonProcess command.

sys.path.append("/usr/share/geni-ch/chapi/chapi")
sys.path.append("/usr/share/geni-ch/chapi/chapi/tools")
sys.path.append("/usr/share/geni-ch/gcf/src")

# Need this for Memoize
sys.path.append("/usr/share/geni-ch/chapi/chapi/plugins/chapiv1rpc")

# Need this for CHDatabaseEngine
sys.path.append("/usr/share/geni-ch/chapi/chapi/plugins/chrm")

from ch_server import application as application
