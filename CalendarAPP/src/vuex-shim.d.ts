import {ComponentCustomProperties} from 'vue'
import {store} from 'vuex'

declare module '@vue/runtime-core' {
    interface State{
        count: number
    }

    interface ComponentCustomProperties{
        $store: store<State>
    }
}