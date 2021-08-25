<template>
  <div id="login">
    <ion-page>
      <ion-content>
    
      <div id="uppercard">
       <h3> Connection :  </h3>
        <div id="card">
       
          <ion-item>
            <ion-label color="dark" position="floating">Utilisateur</ion-label>
            <ion-input
              color="dark"
              type="text"
              v-model="userInfo.username"
              
            ></ion-input>
          </ion-item>
          <ion-item>
            <ion-label color="dark" position="floating">Password</ion-label>
            <ion-input
              color="dark"
              type="password"
              v-model="userInfo.password"
            ></ion-input>
          </ion-item>
        </div>

        <ion-button expand="block" shape="round" fill="outline" @click="login()"
          >Login</ion-button
        >
        </div>
      </ion-content>
    </ion-page>
  </div>
</template>

<script>
import { IonItem, IonLabel, IonInput, IonPage, IonContent } from "@ionic/vue";

import { IonButton, toastController } from "@ionic/vue";

import { Storage } from "@ionic/storage";



import services from "@/service";

export default {
  components: {
    IonItem,
    IonLabel,
    IonInput,
    IonButton,
    IonPage,
    IonContent,
  },
  

  data() {
    return {
      localStorage: new Storage(),
      userInfo: {
        username: "",
        password: "",
      },
    };
  },

 async mounted(){
this.localStorage.create();
this.userInfo.username =    await this.localStorage.get('user');
  },
  methods: {
    
    async login() {
      try {
        let user = await services.call("User", "login", [
          this.userInfo.username,
          this.userInfo.password,
       
        ]);

          await  this.localStorage.set('user',    this.userInfo.username);
        this.$store.commit("setUser",  user );
      
    
      this.$router.push({ name: "fees" });
      } catch (error) {
        this.openToast("Mauvais Login ou mot de passe");
      }
    },
    async openToast(text) {
      const toast = await toastController.create({
        message: text,
        duration: 2000,
      });
      return toast.present();
    },

  },
 
};
</script>
