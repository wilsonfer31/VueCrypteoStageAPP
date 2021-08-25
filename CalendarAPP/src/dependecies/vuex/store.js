import {createStore} from 'vuex'



  export const store = createStore({
    state(){
      return{
        user: "user",
        date: new Date().toISOString().substring(0, 10),
        data : [],
        dataKm: []
      }
    },

    actions: {
  
    },

    mutations: {
      setUser(state,value){
        state.user = value;
      },
      setDate(state,date){
        state.date = date;
      },
      setData(state,data){
        state.data = data;
      },
    
      setDataKM(state,datakm){
        state.data = datakm;
      },
    }
  },
  
  );