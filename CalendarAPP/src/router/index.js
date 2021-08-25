import { createRouter, createWebHistory } from "@ionic/vue-router";
import fees from "../pages/fees.vue";
import login from "../pages/login.vue";
import index from "../pages/index.vue";

const routes = [
 

  {
    path: "/form",
    name: "form",
    component: fees,
  },

  {
    path: "/",
    name: "login",
    component: login,
  },
  {
    path: "/fees",
    name: "fees",
    component: index,
  },
];

const router = createRouter({
  history: createWebHistory(process.env.BASE_URL),
  routes,
});

export default router;
