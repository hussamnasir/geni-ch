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

# This gets called when we invoke any of the CH methods<

# When called, we have an environment with these variables
#    'mod_wsgi.listener_port': '8444'
#    'SERVER_SOFTWARE': 'Apache/2.2.22 (Ubuntu)'
#    'mod_wsgi.process_group': ''
#    'SCRIPT_NAME': '/SR'
#    'mod_wsgi.handler_script': ''
#    'SERVER_SIGNATURE': '<address>Apache/2.2.22 (Ubuntu) Server at ch-mb.gpolab.bbn.com Port 8444</address>\\n'
#    'REQUEST_METHOD': 'POST'
#    'PATH_INFO': ''
#    'SERVER_PROTOCOL': 'HTTP/1.1'
#    'QUERY_STRING': ''
#    'CONTENT_LENGTH': '105'
#    'HTTP_USER_AGENT': 'xmlrpclib.py/1.0.1 (by www.pythonware.com)'
#    'SERVER_NAME': 'ch-mb.gpolab.bbn.com'
#    'REMOTE_ADDR': '128.89.91.46'
#    'mod_wsgi.request_handler': 'wsgi-script'
#    'wsgi.url_scheme': 'https'
#    'mod_wsgi.callable_object': 'application'
#    'SERVER_PORT': '8444'
#    'wsgi.multiprocess': True
#    'mod_wsgi.input_chunked': '0'
#    'SERVER_ADDR': '128.89.91.61'
#    'DOCUMENT_ROOT': '/usr/share/geni-ch/ch/www'
#    'SSL_CLIENT_CERT': ... (PEM of caller's cert)
#    'SCRIPT_FILENAME': '/usr/share/geni-ch/chapi/AMsoil/src/msb.wsgi'
#    'SSL_CLIENT_CERT_CHAIN_0': ... (PEMs of caller's cert parents)
#    'SERVER_ADMIN': 'portal-sandbox-admin@gpolab.bbn.com'
#    'wsgi.input': <mod_wsgi.Input object at 0xb88df700>
#    'HTTP_HOST': 'ch-mb.gpolab.bbn.com:8444'
#    'HTTPS': '1'
#    'wsgi.multithread': False
#    'REQUEST_URI': '/SR'
#    'wsgi.version': (1, 1)
#    'GATEWAY_INTERFACE': 'CGI/1.1'
#    'wsgi.run_once': False
#    'wsgi.errors': <mod_wsgi.Log object at 0xb891bc28>
#    'REMOTE_PORT': '48346'
#    'mod_wsgi.listener_host': ''
#    'mod_wsgi.version': (3, 3)
#    'CONTENT_TYPE': 'text/xml'
#    'mod_wsgi.application_group': 'ch-mb.gpolab.bbn.com:8444|/sr'
#    'mod_wsgi.script_reloading': '1'
#    'wsgi.file_wrapper': <built-in method file_wrapper of mod_wsgi.Adapter object at 0xb8633d10>
#    'HTTP_ACCEPT_ENCODING': 'gzip'
#    'SSL_SERVER_CERT': ... (PEM of server cert

import xmlrpclib

import tools.pluginmanager as pm
pm.registerService('xmlrpc', pm.XMLRPCHandler())
pm.registerService('config', pm.ConfigDB())
pm.registerService('rpcserver', pm.RESTServer())

import plugins.chapiv1rpc.plugin
import plugins.chrm.plugin
import plugins.csrm.plugin
import plugins.flaskrest.plugin
import plugins.logging.plugin
import plugins.opsmon.plugin
import plugins.marm.plugin
import plugins.pgch.plugin
import plugins.sarm.plugin

from tools.chapi_log import *

# import cert_registry

initialized = False

def initialize():
    global initialized
    if not initialized:
        plugins.chapiv1rpc.plugin.setup()
        plugins.chrm.plugin.setup()
        plugins.csrm.plugin.setup()
        plugins.flaskrest.plugin.setup()
        plugins.logging.plugin.setup()
        plugins.opsmon.plugin.setup()
        plugins.marm.plugin.setup()
        plugins.pgch.plugin.setup()
        plugins.sarm.plugin.setup()
        chapi_info("CH_SERVER",  "INITIALIZED CH_SERVER")
        initialized = True

def application(environ, start_response):

    initialize()

#    print "ENV = " + str(environ)
    start_response('200 OK', [('Content-Type', 'text/html')])

    # Try to handle REST invocations, registered with rpcserver
    rest_output = handle_REST_call(environ)
    if rest_output: 
        # It is a REST invocation
        return [rest_output]
    else:
        # Otherwise it is an XMLRPC invocation
        xmlrpc_output = handle_XMLRPC_call(environ)
        return [xmlrpc_output]

# Handle XMLRPC invocation
def handle_XMLRPC_call(environ):

    xmlrpc_endpoint = environ['REQUEST_URI']
    xmlrpc = pm.getService('xmlrpc')
#    print "ENDPOINTS = %s" % xmlrpc._entries_by_endpoint
    handler_entry = xmlrpc.lookupByEndpoint(xmlrpc_endpoint)
    handler = handler_entry._instance
#    print "HANDLER = %s: %s, DIR = %s" % (type(handler), handler, dir(handler))

    length = int(environ['CONTENT_LENGTH'])
    wsgi_input = environ['wsgi.input']
    data = wsgi_input.read(length)
#    print "INPUT = %s: %s %s" % (wsgi_input, type(wsgi_input), dir(wsgi_input))
#    print "DATA = " + data
    decoded_data = xmlrpclib.loads(data)
#    print "DECODED_DATA = " + str(decoded_data)
    args = decoded_data[0]
    method = decoded_data[1]
    fcn = eval("handler.%s" % method)

    # Add calling environment into options argument (if there is one)
    fcn_args = fcn.__code__.co_varnames
    if 'options' in fcn_args:
        options_index = fcn_args.index('options')
        options_index = options_index-1 # Skip over 'self' fcn argument
#        print "ARGS = %s INDEX = %s FCN_ARGS = %s" % (args, options_index, fcn_args)
        if len(args) <= options_index:
            args = ({},) # Empty default options argument
        args[options_index]['ENVIRON'] = environ

        
#    cert_registry.register_client_cert(environ['SSL_CLIENT_CERT'])
#    print "XMLRPC.FCN = %s, ARGS = %s" % (fcn, args)
#    print "LOOKUP-A %s" % cert_registry.lookup_client_cert()

#    print "LOOKUP-A(2) %s" % cert_registry.lookup_client_cert()
#    print "LOOKUP-A(3) %s" % cert_registry.lookup_client_cert()
#    print "LOOKUP-A(4) %s" % cert_registry.lookup_client_cert()
    
#    from plugins.chapiv1rpc.chapi.X import foo
#    print "FOO = %s" % foo()

#    print "ARGS = %s" % (args,)
#    mr = handler.lookup_members(*args)
#    print "MR = %s" % mr
#    import plugins.chapiv1rpc.chapi.SliceAuthority as SA
#    new_sa_handler = SA.SAv1Handler()
#    new_sa_handler.setDelegate(handler.getDelegate())
#    mr2 = new_sa_handler.lookup_members(*args)
#    print "MR2 = %s" % mr2

    method_response = fcn(*args)
#    print "LOOKUP-B %s" % cert_registry.lookup_client_cert()
#    print "RESPONSE = %s" % method_response
    response =  xmlrpclib.dumps((method_response, ), allow_none=True)
    return response

# Determine if this is a REST call. If so, make the call and return output
def handle_REST_call(environ):
    if 'PATH_INFO' in environ:
        path_info = environ['PATH_INFO']
        rpcserver = pm.getService('rpcserver')
        handler = rpcserver.app.lookup_handler(path_info)
        if handler:
#            print "HANDLER = %s" % handler
#            print "HANDLER = %s" % dir(handler)
            pieces = path_info.split('/')
            if len(pieces) < 4 :
                return None
            variety = pieces[2]
            id = pieces[3]
            output = handler(variety, id)
#            print "OUTPUT = " + output
            return output
        return None






