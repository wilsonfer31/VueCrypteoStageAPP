<template>

  <ion-page id="index">
    <ion-header>
      <ion-toolbar> Application de gestion de frais  <ion-label slot="end">{{user.firstname}}</ion-label></ion-toolbar>
       
    </ion-header>

    <ion-content>
      <ion-item>  
        <ion-label>Date</ion-label>
        <ion-datetime
          display-format="DD MM YY"
          placeholder="Choisir Date"
          :value="this.storeDate"
          @ionChange="getdate($event)"
        >  </ion-datetime>
      </ion-item>

      <ion-label position="fixed"></ion-label>

      <ion-list >
        <ion-item>  
          <ion-label>TYPE</ion-label><ion-label slot="end">VALEUR</ion-label>
        </ion-item>

       <ion-item v-for="info in infoDB.items" :key="info">
          <ion-label >{{info.type_of_fees.type}}</ion-label>  <template v-if="!showKM(info.km)" > <ion-label   @click="setData(info); changePage();" slot="end">{{info.ttc}} €</ion-label> </template>    <template v-if="showKM(info.km)"> <ion-label   @click="setData(info); changePage();" slot="end">{{info.km}} KM</ion-label></template>
        </ion-item>

        
          <ion-button size="large" fill="outline" type="submit" @click="changePage();  setData(null); "
            >+ <br />
            Frais</ion-button>
        
      
      </ion-list>
    </ion-content>
             
  </ion-page>

</template>

<script>
import services from "@/service";
import {computed} from 'vue';
import {useStore} from 'vuex';




import { IonPage, IonItem, IonList, IonToolbar,IonDatetime,IonLabel,IonHeader,IonButton,IonContent, toastController} from "@ionic/vue";

export default {
  components: {
    IonPage,
    IonItem,
    IonList,
    IonToolbar,
    IonDatetime,
    IonLabel,
    IonHeader,
    IonButton,
    IonContent,


  },

  setup(){
    const store = useStore();
    return{
      user: computed(()=> store.state.user),
      storeDate: computed(() => store.state.date),
       data: computed(() => store.state.data),
     

    
      
    }
  },

   async ionViewDidEnter() {
     this.myDate = this.storeDate;
    let user = await services.call("User", "getCurrent");
      this.$store.commit("setUser",  user );
     let db = await services.call("Fees", "getAll", {"date": this.storeDate});
      this.infoDB = db;
  },
  data: function() {
    return {
      myDate: "",
      infoDB: [],
     
    };
  },

  mounted() {
    this.load();
    

  },

  methods: {
    
    async load() {
      let user = await services.call("User", "getCurrent");
      this.$store.commit("setUser",  user );
      let db = await services.call("Fees", "getAll", {"date": this.storeDate});
      this.infoDB = db;
    },

    
     async getdate(event) {
      this.myDate = event.target.value.substring(0, 10);
       this.$store.commit("setDate", this.myDate );
      let db = await services.call("Fees", "getAll", {"date": this.storeDate});
      this.infoDB = db;


    },
    changePage(){
        try{
        if (this.myDate != ""){  

        this.$router.push({ name: "form" });
        }else{
          this.openToast("Veuillez saisir une date");
        }
        }catch(error){
          this.openToast(error);  
        }
    },
     async openToast(text) {
      const toast = await toastController.create({
        message: text,
        duration: 2000,
      });
      return toast.present();
    },
    setData(data){
      if(data != null){
        console.log(data);
      this.$store.commit("setData",  data );
      }else{
           this.$store.commit("setData",  null );
      }
    },
    showKM(value){
      if(value){
        return true;
      }else{
        return false;
      }

    }


   
    

 
  },

  

  
};
</script>
