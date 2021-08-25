import axios from 'axios';
import axiosCookieJarSupport from 'axios-cookiejar-support'
//const tough = require('tough-cookie');
axios.defaults.withCredentials = true
axiosCookieJarSupport(axios);
//const cookieJar = new tough.CookieJar();
//import store from './store'
const apiurl = 'http://192.168.155.15/brunerieirissou/calendrier/server/'

var Service = {
	call: function(service, method, args = []) {
		console.log('Call '+service+':'+method+' with ',args)
		//store.commit('busyState', true);
		//console.log('CALL:'+service+' '+method+' '+store.state.busy )
		//store.dispatch('alert', {show:false, variant:'', msg:''});
		return axios.post(apiurl, {
			'service': service,
			'method': method,
			'args': args
		},{
			//jar: cookieJar
		}).then(function(response) { 
			if (typeof response.data != 'object')
				throw new Error('Incorrect answer from server');				
			if (response.data.type == 'error')
				throw new Error(response.data.message);				
			//store.commit('busyState', false);
			//console.log('THEN:'+service+' '+method+' '+store.state.busy)
			return response.data.result;
		}).catch(function(error) {
			//store.commit('busyState', false);			
			//console.log('CATCH:'+service+' '+method+' '+store.state.busy)			
			if (error.response && error.response.data) {
				//if (error.response.data.code == 1) 
				//	store.commit('setConnectedUser', null);
				error = error.response.data.message;				
			}
			else 
				error = error.message;			
			//store.dispatch('alert', {show:true, variant:"danger", msg:error});
			
			throw new Error(error);
		});
	},

	upload: function(fileList) {
		const formData = new FormData();
		if (!fileList || !fileList.length) return [];
		Array.from(Array(fileList.length).keys())
            .map(x => {
                formData.append('file'+x, fileList[x], fileList[x].name);
            });
		return this._upload(formData);
	},

	_upload: function(formData) {
		//store.commit('busyState', true);
		return axios.post(apiurl+'/upload.php', formData)
		.then(function(response) {
			//store.commit('busyState', false);
			if (response.data.success == false)
				throw new Error(response.data.message);
			return response.data.files;
		}).catch(function(error) {
			//store.commit('busyState', false);			
			if (error.response && error.response.data) {
				error = error.response.data.message;				
			}
			else 
				error = error.message;			
			//store.dispatch('alert', {show:true, variant:"danger", msg:error});
			throw new Error(error);
		});
	},

	init: function() {
		return axios.get('api/?api').then(function(response) { 
			var services = response.data;
			var cls = Service;
			var service;
			for (service in services) {
				var target = cls[service];
				if (!target) 
					target = cls[service] = {};	
				for (let method of services[service]) {
					cls[service][method.name] = (function(service, method) {
						return function() {
							Service.call(service, method, [].slice.call(arguments))
						}
					})(service, method.name)
				}
			}
		})
	}
}

export default Service;