import { Component, computed, input } from '@angular/core';

import { ItemInstrumento, RespuestaGuardada } from '../../../core/models/instrumento';
import { SECCIONES_INFORME } from './informes-api';

// HU-19: informe confidencial enviado, en solo lectura (lo ven la Coordinación y el supervisor que lo envió)
@Component({
  selector: 'app-informe-lectura',
  template: `
    @for (seccion of secciones(); track seccion.clave) {
      <section class="tarjeta" [attr.aria-labelledby]="'lectura-' + seccion.clave">
        <div class="tarjeta__cabecera">
          <h2 [id]="'lectura-' + seccion.clave">{{ seccion.titulo }}</h2>
        </div>
        <ol class="lectura">
          @for (item of seccion.items; track item.id) {
            <li class="lectura__fila" [class.lectura__fila--texto]="item.tipo === 'texto'">
              <span class="lectura__enunciado">{{ item.enunciado }}</span>
              <span class="lectura__valor">{{ valor(item) }}</span>
            </li>
          }
        </ol>
      </section>
    }
  `,
  styles: `
    .lectura {
      margin: 0;
      padding: 0;
      list-style: none;
    }

    .lectura__fila {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      gap: 4px 24px;
      padding: 12px 24px;
      font-size: 14px;
      color: #1e293b;

      & + & {
        border-top: 1px solid #e2e8f0;
      }
    }

    .lectura__enunciado {
      flex: 1 1 280px;
    }

    .lectura__valor {
      font-weight: 600;
    }

    .lectura__fila--texto {
      flex-direction: column;

      .lectura__enunciado {
        flex: none;
        color: #5b6b80;
      }

      .lectura__valor {
        font-weight: 400;
        white-space: pre-line;
      }
    }
  `,
})
export class InformeLectura {
  readonly informe = input.required<RespuestaGuardada>();

  protected readonly secciones = computed(() => {
    const items = this.informe().items;
    return SECCIONES_INFORME
      .map((seccion) => ({ ...seccion, items: items.filter((item) => item.seccion === seccion.clave) }))
      .filter((seccion) => seccion.items.length > 0);
  });

  protected valor(item: ItemInstrumento): string {
    const fila = this.informe().respuestas.find((r) => r.item_id === item.id);
    if (item.tipo === 'texto') return fila?.texto || 'Sin comentario';
    return item.opciones.find((o) => o.id === fila?.opcion_id)?.etiqueta ?? 'Sin respuesta';
  }
}
