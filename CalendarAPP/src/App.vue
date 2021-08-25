<template>
  <ion-app>
    <ion-router-outlet />
  </ion-app>
</template>

<script >
import { IonApp, IonRouterOutlet } from '@ionic/vue';
import { defineComponent } from 'vue';
import services from "@/service";

export default defineComponent({
  name: 'App',
  components: {
    IonApp,
    IonRouterOutlet
  }, 
   async mounted() {
    let user = await services.call( "user" , "getCurrent");
      if (user == null){
        this.$router.push({ name: "login" });
      }else{
          this.$router.push({ name: "fees" });
      }
    

  },

    
  

 
  
  
},)

</script>