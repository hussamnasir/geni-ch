#----------------------------------------------------------------------
# Copyright (c) 2011-2016 Raytheon BBN Technologies
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
#----------------------------------------------------------------------

ALL_SERVICES = {}

def registerService(name, service):
    ALL_SERVICES[name] = service

def getService(name):
    return ALL_SERVICES[name]

# Stored in plugin manager as 'xmlrpc' service
class XMLRPCHandler(object):

    _entries = []
    _entries_by_endpoint = {}
    _entries_by_service_name = {}

    def registerXMLRPC(self, unique_service_name, instance, endpoint):
        entry = XMLRPCEntry(unique_service_name, instance, endpoint)
        self._entries.append(entry)
        self._entries_by_endpoint[endpoint] = entry
        self._entries_by_service_name[unique_service_name] = entry

    def lookupByEndpoint(self, endpoint):
        return self._entries_by_endpoint[endpoint]

    def lookupByServiceName(self, service_name):
        return self._entries_by_service_name[service_name]

class XMLRPCEntry(object):
    def __init__(self, unique_service_name, instance, endpoint):
        self._unique_service_name = unique_service_name
        self._instance = instance
        self._endpoint = endpoint

# Stored in plugin manager as 'config' service
class ConfigDB(object):
    _mapping = {}

    def install(self, key, defaultValue, defaultDescription, force=False):
#        print "ConfigDB.install %s %s %s %s" % \
#            (key, defaultValue, defaultDescription, force)
        # ***
        pass

    def set(self, key, value):
        self._mapping[key] = value
        
    def get(self, key):
        return self._mapping[key]

    def getAll(self):
        return self._mapping.keys()

class RESTDispatcher(object):
    def add_url_rule(self, endpoint, rule, handler, defaults, methods):
        print "RESTDispatcher called: %s %s %s %s %s" % \
            (endpoint, rule, handler, defaults, methods)

class RESTServer(object):

    app = RESTDispatcher()
    
    def runServer(self):
        print "FlaskServer.runServer"
        pass
