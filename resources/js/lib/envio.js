import { nextTick, ref } from 'vue'

/**
 * Bloqueo de los botones que graban algo, para que no se dupliquen registros.
 *
 * El botón se deshabilita con el click y vuelve a habilitarse recién cuando el
 * usuario ya tiene el resultado en pantalla:
 *
 *  - si falló, al terminar el request (los errores de validación ya están en
 *    `form.errors`, así que se ven en el mismo tick en que se suelta el botón);
 *  - si salió bien, un tick después, cuando Inertia ya pintó la pantalla nueva
 *    o refrescó el listado. Ese tick es el que evita el segundo POST del doble
 *    click sobre una pantalla que todavía muestra el formulario anterior.
 *
 * Además hay una guarda por código: dos clicks en el mismo tick (doble click
 * rápido, o Enter y click a la vez) llegan antes de que el DOM se entere del
 * `disabled`, y ahí el `:disabled` del template no protege nada.
 *
 * No se libera al desmontar a propósito: si la respuesta navega a otra pantalla
 * el componente se va con el ref adentro.
 *
 * Uso con un useForm:
 *
 *     const { enviando, enviar } = useEnvio()
 *     const guardar = () => enviar((op) => form.post(url, op), { preserveScroll: true })
 *     // en el template: :disabled="enviando"
 *
 * Uso con el router (anular, eliminar y demás acciones sueltas de un listado):
 *
 *     const anular = (id) => enviar((op) => router.post(`${url}/${id}/anular`, {}, op))
 *
 * Un `useEnvio()` por pantalla alcanza: mientras hay una acción en vuelo conviene
 * que queden bloqueadas todas, incluidas las de las otras filas de la tabla.
 *
 * @returns {{enviando: import('vue').Ref<boolean>, enviar: Function, liberar: Function}}
 */
export function useEnvio() {
  const enviando = ref(false)

  /**
   * @param {(opciones: object) => void} accion  El form.post/put o router.* a disparar.
   * @param {object} opciones                    Opciones de la visita de Inertia.
   */
  const enviar = (accion, opciones = {}) => {
    if (enviando.value) return

    enviando.value = true

    let exito = false

    accion({
      ...opciones,
      onSuccess: (pagina) => {
        exito = true
        opciones.onSuccess?.(pagina)
      },
      onFinish: (visita) => {
        // onFinish corre también cuando el server tira 500 o se cae la red: en
        // ese caso Inertia muestra el error y no pasa por onSuccess, así que el
        // botón se libera y el usuario puede reintentar.
        if (exito) {
          nextTick(() => (enviando.value = false))
        } else {
          enviando.value = false
        }

        opciones.onFinish?.(visita)
      },
    })
  }

  /** Escotilla de emergencia para liberar el botón a mano. */
  const liberar = () => (enviando.value = false)

  return { enviando, enviar, liberar }
}
