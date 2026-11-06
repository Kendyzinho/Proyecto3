import { AbstractControl, ValidationErrors } from '@angular/forms';

// Política de contraseñas del backend (HU-02 y HU-04): 8 caracteres, una mayúscula y un número
export function validarNuevaClave(control: AbstractControl<string>): ValidationErrors | null {
  const valor = control.value;
  if (!valor) return null;
  if ([...valor].length < 8) return { longitud: true };
  if (!/\p{Lu}/u.test(valor) || !/[0-9]/.test(valor)) return { complejidad: true };
  if (new TextEncoder().encode(valor).length > 72) return { demasiadoLarga: true };
  return valor.includes('\0') ? { caracterInvalido: true } : null;
}
