import { Injectable } from '@angular/core';
import { MatPaginatorIntl } from '@angular/material/paginator';

// Textos del paginador de Material en español: "1 – 10 de 30"
@Injectable()
export class PaginadorEnEspanol extends MatPaginatorIntl {
  override itemsPerPageLabel = 'Filas por página';
  override nextPageLabel = 'Página siguiente';
  override previousPageLabel = 'Página anterior';
  override firstPageLabel = 'Primera página';
  override lastPageLabel = 'Última página';

  override getRangeLabel = (pagina: number, tamano: number, total: number): string => {
    if (total === 0) {
      return '0 de 0';
    }
    const desde = pagina * tamano + 1;
    const hasta = Math.min(desde + tamano - 1, total);
    return `${desde} – ${hasta} de ${total}`;
  };
}
