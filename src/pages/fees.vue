<template>
  <ion-page id="fees">
    <ion-header>
      <ion-toolbar>
        Application de gestion de frais
        <ion-label slot="end">{{ user.firstname }}</ion-label></ion-toolbar
      >
    </ion-header>

    <ion-content>
      <form ref="form" @submit.prevent="null">
        <ion-item>
          <ion-label>Date</ion-label>
          <ion-datetime
            display-format="DD MM YY"
            placeholder="Choisir Date"
            :value="this.storeDate"
            @ionChange="getdate($event)"
          ></ion-datetime>
        </ion-item>

        <ion-item>
          <ion-label>Type</ion-label>
          <ion-select
            placeholder="Chosir Type"
            interface="action-sheet"
            v-model="fees.type_of_fees_id"
          >
            <ion-item v-for="item in db_types.items" :key="item">
              <ion-select-option :value="item.id">{{
                item.type
              }}</ion-select-option>
            </ion-item>
          </ion-select>
        </ion-item>

        <template v-if="showKM">
          <ion-item>
            <ion-label position="fixed"> Voiture</ion-label>
            <ion-input
              placeholder="Voiture"
              type="text"
              v-model="voiture"
              :required="true"
            >
            </ion-input>
          </ion-item>

          <ion-item>
            <ion-label>Trajet</ion-label>
            <input
              type="text"
              placeholder="Ville"
              v-model="fees.drive_place"
              id="input"
              list="Place"
                :required="true"
            >
                <datalist id="Place">
                  <li   v-for="item in sortedCategories.slice(0, 10)"
                :key="item">
        <option :value="item.name"> {{ item.name }}</option>

                  </li>
    </datalist>
     <ion-select
              placeholder=""
              interface="popover"
              v-model="fees.drive_place"
              @ionChange="getKm($event)"
            >
              <ion-item
                v-for="item in sortedCategories.slice(0, 5)"
                :key="item"
          
              >
                <ion-select-option :value="item.name">
                  {{ item.name }}
                </ion-select-option>
              </ion-item>
            </ion-select>
     </ion-item>
                


          
        


          <ion-item>
            <ion-label position="fixed"> KM</ion-label>
            <ion-input
              placeholder="Montant"
              type="number"
              v-model="fees.km"
              :required="true"
            >
            </ion-input
            >KM
          </ion-item>
        </template>

        <template v-if="!showKM">
          <ion-item>
            <ion-label position="fixed"> TTC</ion-label>
            <ion-input
              placeholder="Montant"
              type="number"
              v-model="fees.ttc"
              :required="true"
            >
            </ion-input
            >€
          </ion-item>
          <ion-item>
            <ion-label position="fixed"> TVA</ion-label>
            <ion-input
              text-end
              placeholder="Montant"
              type="number"
              v-model="fees.tva"
              :required="true"
            >
            </ion-input
            >€
          </ion-item>




        </template>

        <ion-item>
          <input
            @change="changeListener($event)"
            type="file"
            accept="image/*"
            capture="camera"
          />
        </ion-item>
        <ion-item>
          <ion-label class="stacked" position="stacked"> Observation</ion-label>
          <ion-input
            class="inputleft"
            placeholder=""
            type="text"
            v-model="fees.info"
          >
          </ion-input>
        </ion-item>

        <ion-button @click="saveData()" expand="full">Enregistrer</ion-button>
      </form>
    </ion-content>
  </ion-page>
</template>

<script>
import { computed } from "vue";
import services from "@/service";
import { useStore } from "vuex";

import {
  IonPage,
  IonHeader,
  IonToolbar,
  IonContent,
  IonLabel,
  IonDatetime,
  IonItem,
  IonSelectOption,
  IonSelect,
  IonInput,
  IonButton,
  toastController,
  loadingController,
} from "@ionic/vue";
export default {
  components: {
    IonPage,
    IonHeader,
    IonToolbar,
    IonContent,
    IonLabel,
    IonDatetime,
    IonItem,
    IonSelectOption,
    IonSelect,
    IonInput,
    IonButton,
  },

  setup() {
    const store = useStore();

    return {
      user: computed(() => store.state.user),
      storeDate: computed(() => store.state.date),
      data: computed(() => store.state.data),
    };
  },

  data: function() {
    return {
      timeout: { type: Number, default: 5000 },
      fees: {
        date: " ",
        ttc: "",
        tva: "",
        info: "",
        image: "",
        type_of_fees_id: "",
        user_id: "",
        km: "",
        drive_place: "",
        drive: {
          name: "",
          user_id: "",
          km: "",
        },
      },

      voiture: "",

      file: null,
      db_types: [],
      drivedb: [],
    };
  },
  mounted() {
    this.load();
  },
  methods: {
    async load() {
      let db = await services.call("type_of_fee", "getAll", {});
      let db_drive = await services.call("drive", "getAll", {});
      this.drivedb = db_drive;
      this.db_types = db;
      if (this.data) Object.assign(this.fees, this.data);
    },

    async saveData() {
      try {
        this.fees.date = this.storeDate;
        this.fees.user_id = this.user.id;
        this.presentLoading();
        let files = await services.upload(this.file);

        this.fees.image = files;

        if (this.showKM) {
          this.fees.drive = {};
          this.fees.drive.user_id = this.fees.user_id;
          this.fees.drive.km = this.fees.km;
          this.fees.ttc = null; //// TTC NOT DEF
          this.fees.tva = null;
          this.fees.drive.name = this.fees.drive_place;

          await services.call("Drive", "save", this.fees.drive);
        } else {
          this.fees.km = null;
        }
        await services.call("Fees", "save", this.fees);
        this.$router.go(-1);
      } catch (error) {
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

    async getdate(event) {
      this.fees.myDate = event.target.value.substring(0, 10);
      this.$store.commit("setDate", this.fees.myDate);
    },
    getType(type) {
      let typeString = this.db_types.items.find(
        (element) => element.id === type
      );
      return typeString.type;
    },
    changeListener($event) {
      this.file = $event.target.files;
    },
    async presentLoading() {
      const loading = await loadingController.create({
        message: "Attendez...",
        duration: this.timeout,
      });

      await loading.present();

      setTimeout(function() {
        loading.dismiss();
      }, this.timeout);
    },
    reportValidity() {
      this.validated = true;
      return this.$refs.form.reportValidity();
    },
    getKm(event) {
      let typeString = this.drivedb.items.find(
        (element) => element.name === event.detail.value
      );
      this.fees.drive_place = typeString.name;
      this.fees.km = typeString.km;
    },
  },
  computed: {
    showKM() {
      if (!this.db_types.items) return "";
      let type = this.db_types.items.find(
        (element) => element.id === this.fees.type_of_fees_id
      );
      return type && type.type == "KM";
    },

    sortedCategories() {
      const drive = this.drivedb.items.reduce((p, c) => {
        p[c.name] = c;
        return p;
      }, {});
      return Object.values(drive).sort((a, b) => a.name.localeCompare(b.name));
    },
  },
};
</script>
