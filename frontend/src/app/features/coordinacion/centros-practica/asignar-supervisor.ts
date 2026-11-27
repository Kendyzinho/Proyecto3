import { Component, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { Subject, catchError, debounceTime, distinctUntilChanged, map, of, switchMap, tap } from 'rxjs';

import { CentroPractica, CuentaSupervisorResultado } from './centro-practica.model';
import { CentrosPracticaService } from './centros-practica.service';

export interface ResultadoAsignarSupervisor {
  centro: CentroPractica;
  mensaje: string | null;
}

// Al crear uno nuevo se revisa el correo: si ya tiene cuenta, se asigna esa
type EstadoCorreo = 'sin_revisar' | 'buscando' | 'nuevo' | 'interna' | 'repetido' | 'error';

const CORREO = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

// "Agregar supervisor(a)" de la ficha del centro, como "Agregar un estudiante" en la nómina (HU-07):
// primero se busca por nombre o correo entre las cuentas ya creadas (p. ej. desde Usuarios) y se asigna al centro;
// si no está, se crea en el momento. Queda activo si el centro ya puede recibir estudiantes (HU-19).
// Las reglas las revisa el backend
@Component({
  selector: 'app-asignar-supervisor',
  imports: [ReactiveFormsModule, MatButtonModule, MatDialogModule, MatFormFieldModule, MatIconModule, MatInputModule],
  templateUrl: './asignar-supervisor.html',
  styleUrl: './asignar-supervisor.scss',
})
export class AsignarSupervisor {
  private readonly servicio = inject(CentrosPracticaService);
  private readonly referencia = inject(MatDialogRef<AsignarSupervisor, ResultadoAsignarSupervisor>);
  protected readonly centro = inject<CentroPractica>(MAT_DIALOG_DATA);

  protected readonly form = inject(NonNullableFormBuilder).group({
    correo: ['', [Validators.required, Validators.email]],
    nombre: [''],
    cargo: [''],
    unidad: [''],
    telefono: [''],
  });

  // Paso 1: buscar
  protected readonly busqueda = signal('');
  protected readonly buscando = signal(false);
  protected readonly resultados = signal<CuentaSupervisorResultado[]>([]);
  private readonly textos = new Subject<string>();

  // Paso 2: una cuenta elegida o uno nuevo
  protected readonly elegido = signal<Omit<CuentaSupervisorResultado, 'en_este_centro'> | null>(null);
  private readonly nuevo = signal(false);
  protected readonly modo = computed(() => (this.elegido() ? 'elegido' : this.nuevo() ? 'nuevo' : 'buscar'));
  protected readonly estadoCorreo = signal<EstadoCorreo>('sin_revisar');

  protected readonly enviando = signal(false);
  protected readonly errorGeneral = signal<string | null>(null);
  protected readonly puedeGuardar = computed(
    () => !this.enviando() && (this.modo() === 'elegido' || (this.modo() === 'nuevo' && this.estadoCorreo() === 'nuevo')),
  );

  // Igual que el botón "Aprobar" de la ficha: activo y con acuerdo vigente (o interno)
  protected readonly quedaActivo = this.centro.estado === 'Activo' && (this.centro.esInterno || this.centro.acuerdo === 'Vigente');

  constructor() {
    this.textos
      .pipe(
        debounceTime(250),
        switchMap((texto) => {
          if (texto.length < 2) return of([]);
          this.buscando.set(true);
          // Un error en una búsqueda no corta las siguientes
          return this.servicio.buscarCuentasSupervisor(this.centro.id, texto).pipe(
            catchError(() => {
              this.errorGeneral.set('No se pudo buscar. Inténtalo de nuevo.');
              return of([]);
            }),
          );
        }),
        takeUntilDestroyed(),
      )
      .subscribe((resultados) => {
        this.buscando.set(false);
        this.resultados.set(resultados);
      });

    // Correo del supervisor(a) nuevo: si ya tiene cuenta de supervisor(a) se pasa a asignar esa
    this.form.controls.correo.valueChanges
      .pipe(
        map((valor) => valor.trim().toLowerCase()),
        distinctUntilChanged(),
        tap(() => {
          this.estadoCorreo.set('sin_revisar');
          this.errorGeneral.set(null);
        }),
        debounceTime(300),
        switchMap((correo) => {
          if (!this.nuevo() || !CORREO.test(correo)) return of(null);
          this.estadoCorreo.set('buscando');
          return this.servicio.buscarCuentaSupervisor(this.centro.id, correo).pipe(
            map((respuesta) => ({ correo, respuesta })),
            catchError(() => of('error' as const)),
          );
        }),
        takeUntilDestroyed(),
      )
      .subscribe((resultado) => {
        if (resultado === null) return;
        if (resultado === 'error') {
          this.estadoCorreo.set('error');
          return;
        }
        const { correo, respuesta } = resultado;
        if (respuesta.en_este_centro) this.estadoCorreo.set('repetido');
        else if (respuesta.interna) this.estadoCorreo.set('interna');
        else if (respuesta.cuenta) this.elegido.set({ nombre: respuesta.cuenta.nombre, correo, activa: respuesta.cuenta.activa });
        else this.estadoCorreo.set('nuevo');
      });
  }

  protected buscar(texto: string): void {
    this.busqueda.set(texto);
    this.errorGeneral.set(null);
    this.textos.next(texto.trim());
  }

  protected elegir(cuenta: CuentaSupervisorResultado): void {
    if (cuenta.en_este_centro) return;
    this.elegido.set({ nombre: cuenta.nombre, correo: cuenta.correo, activa: cuenta.activa });
  }

  // Crear uno nuevo en el centro. Lo buscado se aprovecha: si parece correo va al correo, si no, al nombre.
  // La revisión del correo espera 300 ms, así que ya corre en modo "nuevo"
  protected empezarNuevo(): void {
    const texto = this.busqueda().trim();
    this.nuevo.set(true);
    this.form.reset();
    this.form.patchValue(texto.includes('@') ? { correo: texto } : { nombre: texto });
  }

  protected volverABuscar(): void {
    this.elegido.set(null);
    this.nuevo.set(false);
    this.estadoCorreo.set('sin_revisar');
    this.errorGeneral.set(null);
  }

  protected guardar(): void {
    const valor = this.form.getRawValue();
    if (this.modo() === 'nuevo' && !valor.nombre.trim()) {
      this.form.controls.nombre.setErrors({ required: true });
      this.form.controls.nombre.markAsTouched();
      return;
    }
    if (!this.puedeGuardar()) return;

    const elegido = this.elegido();
    this.enviando.set(true);
    this.errorGeneral.set(null);
    this.servicio
      .asignarSupervisor(this.centro.id, {
        correo: elegido ? elegido.correo : valor.correo.trim().toLowerCase(),
        nombre: elegido ? null : valor.nombre.trim(),
        cargo: valor.cargo.trim() || null,
        unidad: valor.unidad.trim() || null,
        telefono: valor.telefono.trim() || null,
      })
      .subscribe({
        next: (resultado) => this.referencia.close(resultado),
        error: (error: unknown) => {
          this.enviando.set(false);
          this.errorGeneral.set(this.servicio.mensajeError(error));
        },
      });
  }
}
