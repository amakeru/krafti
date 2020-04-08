export default ({app, store}, inject) => {
  inject('app', {
    header_image: {
      get() {
        return store.getters['app/header_image']
      },
      set(value) {
        if (value !== this.get()) {
          if (value === true) {
            store.commit('app/add_header_image')
          } else {
            store.commit('app/remove_header_image')
          }
        }
      },
    },

    mobile_menu: {
      get() {
        return store.getters['app/mobile_menu']
      },
      set(value) {
        if (value !== this.get()) {
          if (value === true) {
            store.commit('app/open_mobile_menu')
          } else {
            store.commit('app/close_mobile_menu')
          }
        }
      },
    },
  })

  inject('image', (data, size = null, type = null) => {
    const params = [app.$settings.image_url]
    params.push(/^\d+$/.test(data) ? data : data.id)
    if (size) {
      params.push(size)
    }
    if (type) {
      params.push(type)
    }

    let url = params.join('/')
    url += '?t=' + (data.updated_at ? new Date(data.updated_at) : new Date()).getTime()

    return url
  })
}